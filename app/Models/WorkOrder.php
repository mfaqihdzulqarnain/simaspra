<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\PriorityLevel;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Observers\WorkOrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(WorkOrderObserver::class)]
class WorkOrder extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;

    protected $fillable = [
        'number',
        'asset_id',
        'damage_report_id',
        'maintenance_schedule_id',
        'type',
        'priority',
        'description',
        'assigned_to',
        'vendor_id',
        'scheduled_at',
        'started_at',
        'finished_at',
        'status',
        'cost',
        'condition_after',
        'result_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'priority' => PriorityLevel::class,
            'status' => WorkOrderStatus::class,
            'condition_after' => AssetCondition::class,
            'scheduled_at' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->created_by ??= Auth::id();
            $order->number ??= NumberSequence::format('work_order', 'WO');
        });
    }

    /* ---------------------------- Activity Log ----------------------------- */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'cost', 'condition_after', 'finished_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Pemeliharaan');
    }

    /* ------------------------------- Media --------------------------------- */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bukti_perbaikan')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf']);
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WorkOrderStatus::Dilaporkan->value,
            WorkOrderStatus::Ditugaskan->value,
            WorkOrderStatus::Dikerjakan->value,
        ]);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('created_at', $year);
    }

    /* ------------------------------- Method -------------------------------- */

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /** Total biaya perbaikan aset ini sepanjang masa */
    public function assetLifetimeCost(): float
    {
        return (float) static::query()
            ->where('asset_id', $this->asset_id)
            ->where('status', WorkOrderStatus::Selesai)
            ->sum('cost');
    }

    /** Biaya perbaikan sudah melampaui ambang terhadap nilai perolehan */
    public function exceedsRepairThreshold(float $percent = 50.0): bool
    {
        $value = (float) ($this->asset?->acquisition_value ?? 0);

        if ($value <= 0) {
            return false;
        }

        return ($this->assetLifetimeCost() / $value * 100) >= $percent;
    }
}
