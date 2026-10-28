<?php

namespace App\Models;

use App\Enums\DamageSeverity;
use App\Observers\DamageReportObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(DamageReportObserver::class)]
class DamageReport extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'asset_id',
        'consumable_id', 'quantity', 'loan_id',
        'reported_by',
        'reporter_name',
        'reporter_contact',
        'description',
        'severity',
        'reported_at',
        'is_processed',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'severity' => DamageSeverity::class,
            'reported_at' => 'datetime',
            'is_processed' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $report) {
            $report->reported_at ??= now();

            // Laporan dari panel: isi otomatis. Dari scan QR publik: tetap null.
            if (Auth::check()) {
                $report->reported_by ??= Auth::id();
                $report->reporter_name ??= Auth::user()->name;
            }
        });
    }

    /* ------------------------------- Media --------------------------------- */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('foto_kerusakan')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** Alat massal yang rusak atau hilang (tanpa kode aset) */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /** Peminjaman yang mengungkap kerusakan ini */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withoutGlobalScopes();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_processed', false);
    }

    public function scopeSeverity(Builder $query, DamageSeverity $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /* ------------------------------ Aksesor -------------------------------- */

    /** Nama pelapor, apa pun sumbernya */
    public function getReporterLabelAttribute(): string
    {
        return $this->reporter?->name
            ?? $this->reporter_name
            ?? 'Anonim';
    }

    /** "PA-26-MBL-0001 — Kursi Lipat" atau "Tabung Reaksi × 3" */
    public function getItemLabelAttribute(): string
    {
        if ($this->asset) {
            return $this->asset->code.' — '.$this->asset->name;
        }

        if ($this->consumable) {
            return $this->consumable->name.($this->quantity ? ' × '.$this->quantity : '');
        }

        return '—';
    }

    /** Laboratorium terkait: dari peminjamannya, alat massalnya, atau ruang asetnya */
    public function laboratoryId(): ?int
    {
        return $this->loan?->laboratory_id
            ?? $this->consumable?->laboratory_id
            ?? $this->asset?->room?->laboratory_id;
    }

    public function getIsPublicReportAttribute(): bool
    {
        return $this->reported_by === null;
    }
}
