<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depreciation extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'period_year',
        'opening_value',
        'depreciation_amount',
        'accumulated_value',
        'book_value',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'opening_value' => 'decimal:2',
            'depreciation_amount' => 'decimal:2',
            'accumulated_value' => 'decimal:2',
            'book_value' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function scopeYear(Builder $query, int $year): Builder
    {
        return $query->where('period_year', $year);
    }

    /**
     * Hitung penyusutan garis lurus satu tahun untuk satu aset.
     * Idempoten: menjalankan ulang tahun yang sama akan menimpa, bukan menggandakan.
     */
    public static function calculateFor(Asset $asset, int $year): ?self
    {
        $value = (float) $asset->acquisition_value;
        $life = $asset->effectiveUsefulLife();

        if ($value <= 0 || $life <= 0 || $asset->year_acquired > $year) {
            return null;
        }

        $residualPercent = (float) ($asset->category?->residual_percent ?? 0);
        $residual = $value * $residualPercent / 100;
        $annual = ($value - $residual) / $life;

        $elapsed = min($year - $asset->year_acquired + 1, $life);
        $accumulated = min($annual * $elapsed, $value - $residual);

        $previousAccumulated = $accumulated - $annual;
        $opening = $value - max($previousAccumulated, 0);

        return static::updateOrCreate(
            ['asset_id' => $asset->id, 'period_year' => $year],
            [
                'opening_value' => round($opening, 2),
                'depreciation_amount' => round(min($annual, $opening - $residual), 2),
                'accumulated_value' => round($accumulated, 2),
                'book_value' => round($value - $accumulated, 2),
                'calculated_at' => now(),
            ]
        );
    }
}