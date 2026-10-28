<?php

namespace App\Models;

use App\Enums\AssetCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris aset dalam satu peminjaman.
 *
 * Aset tunggal: quantity selalu 1, kerusakan dicatat lewat condition_after.
 * Aset kolektif: quantity bisa lebih dari 1, kerusakan dicatat sebagai
 * jumlah (returned_quantity, damaged_quantity); kondisi aset tidak diubah.
 */
class LoanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'asset_id',
        'quantity',
        'condition_before',
        'condition_after',
        'returned_quantity',
        'damaged_quantity',
        'returned_at',
        'damage_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'condition_before' => AssetCondition::class,
            'condition_after' => AssetCondition::class,
            'returned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            // Bukti kondisi saat keluar — dasar klaim kerusakan nanti
            $item->condition_before ??= $item->asset?->condition;
            $item->quantity = max(1, (int) ($item->quantity ?? 1));
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function scopeNotReturned(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    /** Baris ini dicatat per jumlah, bukan per kondisi */
    public function isCollective(): bool
    {
        return (bool) $this->asset?->isCollective();
    }

    /** Hilang: tidak kembali, baik utuh maupun rusak */
    public function getLostQuantityAttribute(): int
    {
        if ($this->returned_quantity === null) {
            return 0;
        }

        return max($this->quantity - $this->returned_quantity - (int) $this->damaged_quantity, 0);
    }

    /** Ringkasan hasil pengembalian aset kolektif: "47 baik, 3 rusak" */
    public function getReturnSummaryAttribute(): ?string
    {
        if ($this->returned_quantity === null) {
            return null;
        }

        return collect([
            $this->returned_quantity.' baik',
            $this->damaged_quantity ? $this->damaged_quantity.' rusak' : null,
            $this->lost_quantity ? $this->lost_quantity.' hilang' : null,
        ])->filter()->implode(', ');
    }

    /** Ada kerusakan atau kehilangan selama dipinjam */
    public function getIsDamagedAttribute(): bool
    {
        if ($this->isCollective()) {
            return (int) $this->damaged_quantity > 0 || $this->lost_quantity > 0;
        }

        if (! $this->condition_after || ! $this->condition_before) {
            return false;
        }

        $rank = [
            AssetCondition::Baik->value => 0,
            AssetCondition::RusakRingan->value => 1,
            AssetCondition::RusakBerat->value => 2,
        ];

        return $rank[$this->condition_after->value] > $rank[$this->condition_before->value];
    }
}
