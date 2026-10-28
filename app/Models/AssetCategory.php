<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'useful_life_years',
        'residual_percent',
        'requires_calibration',
        'calibration_interval_months',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'useful_life_years' => 'integer',
            'residual_percent' => 'decimal:2',
            'requires_calibration' => 'boolean',
            'calibration_interval_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function consumables(): HasMany
    {
        return $this->hasMany(Consumable::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getFullNameAttribute(): string
    {
        return $this->parent
            ? "{$this->parent->name} / {$this->name}"
            : $this->name;
    }
}
