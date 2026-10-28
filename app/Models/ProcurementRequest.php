<?php

namespace App\Models;

use App\Enums\PriorityLevel;
use App\Enums\RequestStatus;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProcurementRequest extends Model
{
    use BelongsToUnit;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'request_number',
        'unit_id',
        'requested_by',
        'budget_year',
        'priority',
        'needed_at',
        'purpose',
        'status',
        'total_estimated',
        'submitted_at',
        'verified_by',
        'verified_at',
        'verification_note',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'budget_year' => 'integer',
            'priority' => PriorityLevel::class,
            'status' => RequestStatus::class,
            'needed_at' => 'date',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'total_estimated' => 'decimal:2',
        ];
    }

    /* ---------------------------- Activity Log ----------------------------- */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Pengajuan');
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class);
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

    public function scopeStatus(Builder $query, RequestStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeAwaitingRole(Builder $query, string $role): Builder
    {
        $statuses = collect(RequestStatus::cases())
            ->filter(fn (RequestStatus $s) => $s->actorRole() === $role)
            ->map(fn (RequestStatus $s) => $s->value)
            ->all();

        return $query->whereIn('status', $statuses);
    }

    public function scopeYear(Builder $query, int $year): Builder
    {
        return $query->where('budget_year', $year);
    }

    /* ------------------------------- Method -------------------------------- */

    public function recalculateTotal(): void
    {
        $this->forceFill([
            'total_estimated' => $this->items()->sum('estimated_total'),
        ])->saveQuietly();
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}