<?php

namespace App\Models;

use App\Enums\AssetCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'handover_id',
        'asset_id',
        'quantity',
        'condition',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'condition' => AssetCondition::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            // Rekam kondisi aset saat serah terima, jadi bukti historis
            $item->condition ??= $item->asset?->condition;
        });
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
