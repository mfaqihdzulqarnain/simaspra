<?php

namespace App\Models;

use App\Enums\ConsumableType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumable extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'asset_category_id',
        'unit_of_measure',
        'min_stock',
        'current_stock',
        'unit_price',
        'storage_room_id',
        'notes',
        'is_active',
        'type', 'laboratory_id', 'on_loan',
        'unit_id',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:2',
            'current_stock' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'type' => ConsumableType::class,
            'on_loan' => 'integer',
        ];
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function storageRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'storage_room_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ConsumableMovement::class)->latest('moved_at');
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanConsumableItem::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock')
            ->where('is_active', true);
    }

    public function scopeLoanable(Builder $query): Builder
    {
        return $query->where('type', ConsumableType::AlatMassal)
            ->where('is_active', true);
    }

    public function scopeConsumed(Builder $query): Builder
    {
        return $query->where('type', ConsumableType::HabisPakai);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Batasi daftar barang sesuai wewenang pengguna.
     *   Sarpras, Wadir, Pimpinan, Super Admin → semua
     *   Laboran → barang di laboratorium yang diampunya
     *   Lainnya → barang milik unitnya, termasuk milik lab di bawah unit itu
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->canSeeAllUnits()) {
            return $query;
        }

        if ($user->hasRole('laboran')) {
            $labIds = $user->laboratoryIds();

            return empty($labIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('laboratory_id', $labIds);
        }

        if (! $user->unit_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q
            ->where('unit_id', $user->unit_id)
            ->orWhereIn('laboratory_id', Laboratory::query()
                ->where('unit_id', $user->unit_id)
                ->select('id')));
    }

    public function scopeInLaboratory(Builder $query, int $laboratoryId): Builder
    {
        return $query->where('laboratory_id', $laboratoryId);
    }

    /* ------------------------------- Method -------------------------------- */

    /** Hitung ulang stok dari seluruh riwayat mutasi */
    public function recalculateStock(): void
    {
        $last = $this->movements()->orderByDesc('moved_at')->orderByDesc('id')->first();

        $this->forceFill([
            'current_stock' => $last?->stock_after ?? 0,
        ])->saveQuietly();
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getIsLowAttribute(): bool
    {
        return (float) $this->current_stock <= (float) $this->min_stock;
    }

    public function getStockValueAttribute(): float
    {
        return (float) $this->current_stock * (float) $this->unit_price;
    }

    /** Stok yang benar-benar bisa dipinjam saat ini */
    public function getAvailableStockAttribute(): int
    {
        return max((int) $this->current_stock - (int) $this->on_loan, 0);
    }

    public function isLoanable(): bool
    {
        return $this->type?->isLoanable() && $this->is_active;
    }
}
