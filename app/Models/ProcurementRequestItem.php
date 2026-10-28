<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_request_id',
        'asset_category_id',
        'name',
        'specification',
        'quantity',
        'unit_of_measure',
        'estimated_unit_price',
        'estimated_total',
        'approved_quantity',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'approved_quantity' => 'integer',
            'estimated_unit_price' => 'decimal:2',
            'estimated_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Total item selalu turunan, tidak pernah diketik manual
        static::saving(function (self $item) {
            $item->estimated_total = (float) $item->quantity * (float) $item->estimated_unit_price;
        });

        static::saved(fn (self $item) => $item->procurementRequest?->recalculateTotal());
        static::deleted(fn (self $item) => $item->procurementRequest?->recalculateTotal());
    }

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /** Aset yang lahir dari item pengajuan ini */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'procurement_request_item_id');
    }
}
