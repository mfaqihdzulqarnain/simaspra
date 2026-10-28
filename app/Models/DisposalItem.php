<?php

namespace App\Models;

use App\Enums\AssetCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisposalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'disposal_id',
        'asset_id',
        'condition',
        'book_value',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'condition' => AssetCondition::class,
            'book_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            $asset = $item->asset;

            $item->condition ??= $asset?->condition;
            $item->book_value ??= $asset?->currentBookValue() ?? 0;
        });

        static::saved(fn (self $item) => $item->disposal?->recalculateBookValue());
        static::deleted(fn (self $item) => $item->disposal?->recalculateBookValue());
    }

    public function disposal(): BelongsTo
    {
        return $this->belongsTo(Disposal::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}