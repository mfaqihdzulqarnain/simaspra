<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'number',
        'type',
        'title',
        'start_date',
        'end_date',
        'value',
        'reminder_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'value' => 'decimal:2',
            'reminder_days' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
        });
    }

    /** Kontrak yang akan habis dalam rentang reminder_days */
    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->whereNotNull('end_date')
            ->whereRaw('DATE_SUB(end_date, INTERVAL reminder_days DAY) <= ?', [now()->toDateString()])
            ->where('end_date', '>=', now()->toDateString());
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        return $this->end_date
            ? now()->startOfDay()->diffInDays($this->end_date, false)
            : null;
    }
}
