<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * SK biaya sewa ruang. SK yang berlaku pada suatu tanggal adalah SK dengan
 * tanggal berlaku paling akhir yang tidak melewati tanggal itu. SK lama
 * tetap tersimpan sebagai arsip dan dasar reservasi yang sudah ditagih.
 */
class RentalDecree extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'number',
        'title',
        'decree_date',
        'effective_from',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'decree_date' => 'date',
            'effective_from' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $decree) {
            if (Auth::check()) {
                $decree->created_by ??= Auth::id();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'title', 'decree_date', 'effective_from'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Tarif Sewa');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('dokumen')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function rates(): HasMany
    {
        return $this->hasMany(RentalRate::class)->orderBy('room_id')->orderBy('price');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeEffectiveOn(Builder $query, CarbonInterface|string|null $date = null): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', Carbon::parse($date ?? now()))
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    /* ------------------------------- Method -------------------------------- */

    /** SK yang berlaku pada tanggal tertentu */
    public static function current(CarbonInterface|string|null $date = null): ?self
    {
        return static::query()->effectiveOn($date)->first();
    }

    public function isCurrent(): bool
    {
        return static::current()?->is($this) ?? false;
    }

    public function documentUrl(): ?string
    {
        return $this->getFirstMediaUrl('dokumen') ?: null;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->number.' — '.$this->title;
    }
}
