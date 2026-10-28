<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumableMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumable_id',
        'type',
        'quantity',
        'stock_after',
        'unit_id',
        'reference',
        'moved_at',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:2',
            'stock_after' => 'decimal:2',
            'moved_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $movement) {
            $movement->created_by ??= Auth::id();
            $movement->moved_at ??= now()->toDateString();

            // Kunci baris consumable agar stok tidak kacau saat transaksi bersamaan
            DB::transaction(function () use ($movement) {
                $consumable = Consumable::query()
                    ->whereKey($movement->consumable_id)
                    ->lockForUpdate()
                    ->first();

                $current = (float) ($consumable?->current_stock ?? 0);
                $qty = (float) $movement->quantity;

                $movement->stock_after = match ($movement->type) {
                    StockMovementType::Masuk => $current + $qty,
                    StockMovementType::Keluar => $current - $qty,
                    StockMovementType::Penyesuaian => $qty,
                };
            });
        });

        static::created(function (self $movement) {
            $movement->consumable?->forceFill([
                'current_stock' => $movement->stock_after,
            ])->saveQuietly();
        });

        // Mutasi dihapus: hitung ulang dari riwayat, jangan tebak
        static::deleted(fn (self $movement) => $movement->consumable?->recalculateStock());
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeType(Builder $query, StockMovementType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBetween(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('moved_at', [$start, $end]);
    }
}