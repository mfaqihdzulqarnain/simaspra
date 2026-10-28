<?php

namespace App\Models;

use App\Enums\HandoverType;
use App\Models\Concerns\HasPublicCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Models\Scopes\HandoverVisibilityScope;

class Handover extends Model
{
    use HasFactory;
    use HasPublicCode;
    use LogsActivity;

    protected $fillable = [
        'number',
        'type',
        'reference_type',
        'reference_id',
        'from_user_id',
        'to_user_id',
        'unit_id',
        'handover_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => HandoverType::class,
            'handover_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new HandoverVisibilityScope);
        static::creating(function (self $handover) {
            $handover->created_by ??= Auth::id();
            $handover->handover_date ??= now()->toDateString();
            $handover->public_code ??= self::generatePublicCode();

            if (blank($handover->number)) {
                $type = $handover->type instanceof HandoverType
                    ? $handover->type
                    : HandoverType::from($handover->type);

                $handover->number = NumberSequence::format(
                    $type->sequenceType(),
                    $type->numberPrefix(),
                );
            }
        });
    }

    /* ---------------------------- Activity Log ----------------------------- */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Serah Terima');
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getPublicUrlAttribute(): string
    {
        return route('bast.print', $this);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HandoverItem::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeType(Builder $query, HandoverType $type): Builder
    {
        return $query->where('type', $type);
    }

    /* ------------------------------- Method -------------------------------- */

    public function documentTitle(): string
    {
        return $this->type->documentTitle();
    }
}