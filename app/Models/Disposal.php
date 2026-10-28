<?php

namespace App\Models;

use App\Enums\DisposalMethod;
use App\Enums\DisposalReason;
use App\Enums\DisposalStatus;
use App\Models\Concerns\BelongsToUnit;
use App\Observers\DisposalObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(DisposalObserver::class)]
class Disposal extends Model implements HasMedia
{
    use BelongsToUnit;
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'disposal_number',
        'unit_id',
        'proposed_by',
        'reason',
        'description',
        'status',
        'inspected_by',
        'inspected_at',
        'inspection_note',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'method',
        'executed_at',
        'execution_note',
        'total_book_value',
        'revenue_amount',
    ];

    protected function casts(): array
    {
        return [
            'reason' => DisposalReason::class,
            'status' => DisposalStatus::class,
            'method' => DisposalMethod::class,
            'inspected_at' => 'datetime',
            'approved_at' => 'datetime',
            'executed_at' => 'date',
            'total_book_value' => 'decimal:2',
            'revenue_amount' => 'decimal:2',
        ];
    }

    /* ---------------------------- Activity Log ----------------------------- */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_by', 'method', 'executed_at', 'revenue_amount'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Penghapusan');
    }

    /* ------------------------------- Media --------------------------------- */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('dokumen_penghapusan')
            ->useDisk('public')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DisposalItem::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function handovers(): MorphMany
    {
        return $this->morphMany(Handover::class, 'reference');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeStatus(Builder $query, DisposalStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeAwaitingRole(Builder $query, string $role): Builder
    {
        $statuses = collect(DisposalStatus::cases())
            ->filter(fn (DisposalStatus $s) => $s->actorRole() === $role)
            ->map(fn (DisposalStatus $s) => $s->value)
            ->all();

        return $query->whereIn('status', $statuses);
    }

    /* ------------------------------- Method -------------------------------- */

    public function recalculateBookValue(): void
    {
        $this->forceFill([
            'total_book_value' => $this->items()->sum('book_value'),
        ])->saveQuietly();
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
