<?php

namespace App\Models;

use App\Enums\OpnameItemStatus;
use App\Enums\OpnameStatus;
use App\Models\Concerns\HasPublicCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class StockOpname extends Model
{
    use HasFactory;
    use HasPublicCode;

    protected $fillable = [
        'code',
        'name',
        'period_start',
        'period_end',
        'unit_id',
        'building_id',
        'status',
        'created_by',
        'closed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => OpnameStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $opname) {
            $opname->created_by ??= Auth::id();
            $opname->code ??= NumberSequence::format('opname', 'OPN');
            $opname->public_code ??= self::generatePublicCode();
        });
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', OpnameStatus::Berjalan);
    }

    /* ------------------------------- Method -------------------------------- */

    /** Aset yang seharusnya ada dalam cakupan opname ini */
    public function expectedAssets(): Builder
    {
        $query = Asset::query()->allUnits()->active();

        if ($this->unit_id) {
            $query->where('unit_id', $this->unit_id);
        }

        if ($this->building_id) {
            $query->whereHas('room', fn (Builder $q) => $q->where('building_id', $this->building_id));
        }

        return $query;
    }

    /**
     * Buat baris periksa untuk seluruh aset dalam cakupan.
     * Dipanggil saat opname dimulai.
     */
    public function generateItems(): int
    {
        $count = 0;

        $this->expectedAssets()->chunkById(200, function ($assets) use (&$count) {
            $rows = $assets->map(fn (Asset $asset) => [
                'stock_opname_id' => $this->id,
                'asset_id' => $asset->id,
                'expected_room_id' => $asset->room_id,
                // Aset kolektif: jumlah menurut catatan saat opname dibuka
                'expected_quantity' => $asset->isCollective() ? (int) $asset->quantity : null,
                'status' => OpnameItemStatus::TidakDitemukan->value,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            StockOpnameItem::insert($rows);
            $count += count($rows);
        });

        return $count;
    }

    public function start(): void
    {
        if ($this->items()->doesntExist()) {
            $this->generateItems();
        }

        $this->forceFill(['status' => OpnameStatus::Berjalan])->save();
    }

    public function close(): void
    {
        $this->forceFill([
            'status' => OpnameStatus::Selesai,
            'closed_at' => now(),
        ])->save();
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getSummaryAttribute(): array
    {
        return $this->items()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function getProgressPercentAttribute(): float
    {
        $total = $this->items()->count();

        if ($total === 0) {
            return 0;
        }

        $checked = $this->items()->whereNotNull('checked_at')->count();

        return round($checked / $total * 100, 1);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('opname.print', $this);
    }
}
