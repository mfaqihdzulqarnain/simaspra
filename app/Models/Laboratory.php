<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Laboratory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'unit_id',
        'head_user_id',
        'opens_at',
        'closes_at',
        'student_max_hours',
        'lecturer_max_hours',
        'requires_approval',
        'rules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'student_max_hours' => 'integer',
            'lecturer_max_hours' => 'integer',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** Laboran yang ditugaskan */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'laboratory_user')->withTimestamps();
    }

    public function assets(): HasManyThrough
    {
        return $this->hasManyThrough(Asset::class, Room::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /* ------------------------------- Method -------------------------------- */

    public function isOpenAt(CarbonInterface $waktu): bool
    {
        if (blank($this->opens_at) || blank($this->closes_at)) {
            return true;
        }

        $jam = $waktu->format('H:i:s');

        return $jam >= $this->opens_at && $jam <= $this->closes_at;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }
}