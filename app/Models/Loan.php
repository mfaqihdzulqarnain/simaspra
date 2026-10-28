<?php

namespace App\Models;

use App\Enums\BorrowerType;
use App\Enums\LoanPurpose;
use App\Enums\LoanStatus;
use App\Models\LoanConsumableItem;
use App\Models\Scopes\LoanVisibilityScope;
use App\Observers\LoanObserver;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[ObservedBy(LoanObserver::class)]
class Loan extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'loan_number',
        'borrower_type',
        'user_id',
        'laboratory_id',
        'borrower_name',
        'borrower_identity',
        'borrower_institution',
        'borrower_phone',
        'borrower_email',
        'purpose',
        'purpose_type',
        'course_name',
        'supervisor_id',
        'use_location',
        'start_at',
        'end_at',
        'returned_at',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'deposit_amount',
        'fee_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'borrower_type' => BorrowerType::class,
            'purpose_type' => LoanPurpose::class,
            'status' => LoanStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'returned_at' => 'datetime',
            'approved_at' => 'datetime',
            'deposit_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Pembatasan baris: lihat Pekerjaan 1
        static::addGlobalScope(new LoanVisibilityScope);
    }

    /* ---------------------------- Activity Log ----------------------------- */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_by', 'returned_at', 'end_at', 'laboratory_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Peminjaman');
    }

    /* ------------------------------ Mutator -------------------------------- */

    /** Kolom NOT NULL: nomor kosong disimpan sebagai string kosong */
    protected function borrowerPhone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Phone::normalize($value) ?? '',
        );
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    /** Reservasi ruang yang menjadi induk peminjaman pelengkap ini */
    public function roomBooking(): HasOne
    {
        return $this->hasOne(RoomBooking::class)
            ->withoutGlobalScope(Scopes\RoomBookingVisibilityScope::class);
    }

    /**
     * Pelengkap reservasi dikelola dari halaman reservasi:
     * persetujuan, perubahan, dan penghapusan tidak dilakukan di sini.
     */
    public function isBookingEquipment(): bool
    {
        return $this->roomBooking()->exists();
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function assets(): HasManyThrough
    {
        return $this->hasManyThrough(Asset::class, LoanItem::class, 'loan_id', 'id', 'id', 'asset_id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function handovers(): MorphMany
    {
        return $this->morphMany(Handover::class, 'reference');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(LoanReminder::class)->latest('sent_at');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeOngoing(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LoanStatus::Dipinjam->value,
            LoanStatus::Terlambat->value,
        ]);
    }

    /** Masih berstatus Dipinjam padahal sudah lewat jatuh tempo */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Dipinjam)
            ->where('end_at', '<', now());
    }

    /** Jatuh tempo dalam N jam ke depan */
    public function scopeDueSoon(Builder $query, int $hours = 24): Builder
    {
        return $query->where('status', LoanStatus::Dipinjam)
            ->whereBetween('end_at', [now(), now()->addHours($hours)]);
    }

    public function scopeInLaboratory(Builder $query, int $laboratoryId): Builder
    {
        return $query->where('laboratory_id', $laboratoryId);
    }

    /* ------------------------------- Method -------------------------------- */

    /**
     * Jumlah unit tiap aset yang sudah terpakai pada rentang waktu tertentu.
     *
     * Memakai daftar id pinjaman tanpa scope visibilitas. Bila scope ikut
     * berlaku, mahasiswa hanya melihat pinjamannya sendiri, dan alat yang
     * sedang dipinjam orang lain akan tampak tersedia.
     *
     * @return array<int, int>  [asset_id => jumlah terpakai]
     */
    public static function reservedQuantities(
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $exceptLoanId = null,
    ): array {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        $loanIds = static::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->whereIn('status', [
                LoanStatus::Diajukan->value,
                LoanStatus::Disetujui->value,
                LoanStatus::Dipinjam->value,
                LoanStatus::Terlambat->value,
            ])
            // Irisan rentang. Pembanding ketat agar sesi yang bersambung
            // (08.00–10.00 lalu 10.00–12.00) tidak dianggap bentrok.
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->when($exceptLoanId, fn (Builder $q) => $q->whereKeyNot($exceptLoanId))
            ->pluck('id');

        if ($loanIds->isEmpty()) {
            return [];
        }

        return LoanItem::query()
            ->whereIn('loan_id', $loanIds)
            ->selectRaw('asset_id, SUM(quantity) as terpakai')
            ->groupBy('asset_id')
            ->pluck('terpakai', 'asset_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Aset yang seluruh unitnya sudah terpakai pada rentang waktu tertentu.
     * Aset tunggal: terpakai sekali saja sudah penuh.
     * Aset kolektif: penuh bila jumlah terpakai mencapai jumlah unitnya.
     *
     * @return array<int>
     */
    public static function conflictingAssetIds(
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $exceptLoanId = null,
    ): array {
        $terpakai = static::reservedQuantities($start, $end, $exceptLoanId);

        if (empty($terpakai)) {
            return [];
        }

        $jumlah = Asset::query()
            ->allUnits()
            ->withTrashed()
            ->whereIn('id', array_keys($terpakai))
            ->pluck('quantity', 'id');

        return collect($terpakai)
            ->filter(fn (int $pakai, int $id) => $pakai >= max(1, (int) ($jumlah[$id] ?? 1)))
            ->keys()
            ->all();
    }

    public function isOngoing(): bool
    {
        return $this->status->isOngoing();
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, [LoanStatus::Dipinjam, LoanStatus::Terlambat], true)
            && $this->end_at?->isPast();
    }

    public function getDaysLateAttribute(): int
    {
        return $this->is_overdue ? (int) $this->end_at->diffInDays(now()) : 0;
    }

    /** Lama keterlambatan yang mudah dibaca: "2 jam 15 menit" */
    public function getLateLabelAttribute(): ?string
    {
        if (! $this->is_overdue) {
            return null;
        }

        return $this->end_at->locale('id')->diffForHumans(now(), [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
        ]);
    }

    public function consumableItems(): HasMany
    {
        return $this->hasMany(LoanConsumableItem::class);
    }

    public function getTotalChargeAttribute(): float
    {
        return (float) $this->fee_amount;
    }
}