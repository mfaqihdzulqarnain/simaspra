<?php

namespace App\Models;

use App\Enums\WorkOrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'asset_category_id',
        'name',
        'type',
        'interval_months',
        'last_done_at',
        'next_due_at',
        'assigned_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'interval_months' => 'integer',
            'last_done_at' => 'date',
            'next_due_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $schedule) {
            $schedule->next_due_at ??= now()->addMonths($schedule->interval_months)->toDateString();
        });
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Jatuh tempo dalam N hari ke depan, termasuk yang lewat */
    public function scopeDue(Builder $query, int $days = 0): Builder
    {
        return $query->where('is_active', true)
            ->where('next_due_at', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('next_due_at', '<', now()->toDateString());
    }

    /* ------------------------------- Method -------------------------------- */

    /** Panggil setelah work order selesai — geser jadwal berikutnya */
    public function markDone(?string $doneAt = null): void
    {
        $doneAt ??= now()->toDateString();

        $this->forceFill([
            'last_done_at' => $doneAt,
            'next_due_at' => now()->parse($doneAt)
                ->addMonths($this->interval_months)
                ->toDateString(),
        ])->save();
    }

    /** Aset yang tercakup jadwal ini */
    public function coveredAssets(): Builder
    {
        if ($this->asset_id) {
            return Asset::query()->whereKey($this->asset_id);
        }

        return Asset::query()
            ->where('asset_category_id', $this->asset_category_id)
            ->active();
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getIsOverdueAttribute(): bool
    {
        return $this->is_active && $this->next_due_at?->isPast();
    }

    public function getDaysUntilDueAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->next_due_at, false);
    }
}