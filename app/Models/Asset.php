<?php

namespace App\Models;

use App\Enums\AcquisitionSource;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LoanableBy;
use App\Models\Concerns\BelongsToUnit;
use App\Models\Concerns\HasPublicCode;
use App\Observers\AssetObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ObservedBy(AssetObserver::class)]
class Asset extends Model implements HasMedia
{
    use BelongsToUnit;
    use HasFactory;
    use HasPublicCode;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'quantity',
        'asset_category_id',
        'unit_id',
        'room_id',
        'vendor_id',
        'contract_id',
        'procurement_request_item_id',
        'brand',
        'model',
        'serial_number',
        'year_acquired',
        'acquisition_date',
        'acquisition_source',
        'acquisition_value',
        'useful_life_years',
        'condition',
        'status',
        'warranty_until',
        'calibration_due_at',
        'is_loanable',
        'notes',
        'created_by',
        'loanable_by',
    ];

    protected function casts(): array
    {
        return [
            'year_acquired' => 'integer',
            'acquisition_date' => 'date',
            'acquisition_source' => AcquisitionSource::class,
            'acquisition_value' => 'decimal:2',
            'useful_life_years' => 'integer',
            'condition' => AssetCondition::class,
            'status' => AssetStatus::class,
            'warranty_until' => 'date',
            'calibration_due_at' => 'date',
            'is_loanable' => 'boolean',
            'label_printed_at' => 'datetime',
            'label_print_count' => 'integer',
            'loanable_by' => LoanableBy::class,
            'quantity' => 'integer',
        ];
    }

    /* ---------------------------- Activity Log ----------------------------- */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'condition', 'status', 'unit_id', 'room_id',
                'acquisition_value', 'is_loanable', 'quantity',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Aset');
    }

    /* ------------------------------- Media --------------------------------- */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('foto')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('dokumen')
            ->useDisk('public')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(240)
            ->height(240)
            ->nonQueued();
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestItem::class, 'procurement_request_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest('moved_at');
    }

    public function handoverItems(): HasMany
    {
        return $this->hasMany(HandoverItem::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class)->latest('reported_at');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->latest();
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function disposalItems(): HasMany
    {
        return $this->hasMany(DisposalItem::class);
    }

    public function opnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(Depreciation::class)->orderByDesc('period_year');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            AssetStatus::Dihapuskan->value,
            AssetStatus::Hilang->value,
        ]);
    }

    public function scopeAvailableForLoan(Builder $query): Builder
    {
        return $query->where('is_loanable', true)
            ->where('status', AssetStatus::Aktif)
            ->where('condition', AssetCondition::Baik);
    }

    public function scopeUnlabeled(Builder $query): Builder
    {
        return $query->whereNull('label_printed_at');
    }

    public function scopeWarrantyExpiring(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('warranty_until')
            ->whereBetween('warranty_until', [
                now()->toDateString(),
                now()->addDays($days)->toDateString(),
            ]);
    }

    public function scopeCalibrationDue(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('calibration_due_at')
            ->where('calibration_due_at', '<=', now()->addDays($days)->toDateString());
    }

    /** Aset yang berada di ruang milik lab tertentu */
    public function scopeInLaboratory(Builder $query, int $laboratoryId): Builder
    {
        return $query->whereHas('room', fn (Builder $q) => $q->where('laboratory_id', $laboratoryId));
    }

    /** Saring alat khusus dosen dari mahasiswa */
    public function scopeLoanableFor(Builder $query, ?User $user): Builder
    {
        if ($user?->hasRole('mahasiswa')) {
            return $query->where('loanable_by', LoanableBy::Semua);
        }

        return $query;
    }

    /** Aset kolektif: satu kode untuk banyak unit */
    public function scopeCollective(Builder $query): Builder
    {
        return $query->where('quantity', '>', 1);
    }

    /**
     * Laboran melihat alat di lab yang ditugaskan kepadanya, lintas unit.
     * Dipanggil BelongsToUnit sebelum penyaringan unit berjalan.
     */
    public static function applyUnitVisibility(Builder $query, User $user): bool
    {
        if (! $user->hasRole('laboran')) {
            return false;
        }

        $labIds = $user->laboratoryIds();

        if (empty($labIds)) {
            $query->whereRaw('1 = 0');

            return true;
        }

        $query->whereHas('room', fn (Builder $q) => $q->whereIn('laboratory_id', $labIds));

        return true;
    }

    /* ------------------------------- Method -------------------------------- */

    public function qrPayload(): string
    {
        return $this->public_url;
    }

    public function effectiveUsefulLife(): int
    {
        return $this->useful_life_years
            ?? $this->category?->useful_life_years
            ?? 4;
    }

    public function currentBookValue(): float
    {
        return (float) ($this->depreciations()->first()?->book_value
            ?? $this->acquisition_value);
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_until && $this->warranty_until->isFuture();
    }

    public function markLabelPrinted(): void
    {
        $this->forceFill([
            'label_printed_at' => now(),
            'label_print_count' => $this->label_print_count + 1,
        ])->saveQuietly();
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }

    public function isCollective(): bool
    {
        return $this->quantity > 1;
    }

    /** Nilai per unit; acquisition_value selalu menyimpan nilai total */
    public function unitValue(): float
    {
        return (float) $this->acquisition_value / max(1, $this->quantity);
    }
}
