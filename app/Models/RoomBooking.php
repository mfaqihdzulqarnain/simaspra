<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\BorrowerType;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Models\Scopes\RoomBookingVisibilityScope;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Pemesanan waktu atas sebuah ruang: kelas pengganti, rapat, atau acara */
class RoomBooking extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'booking_number',
        'room_id',
        'loan_id',
        'booking_type',
        'title',
        'description',
        'attendees',
        'start_at',
        'end_at',
        'borrower_type',
        'user_id',
        'borrower_name',
        'borrower_institution',
        'borrower_phone',
        'borrower_email',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'cancellation_reason',
        'notes',
        'created_by',
        'fee_amount',
        'rental_decree_id',
        'fee_breakdown',
        'payment_status',
        'paid_at',
        'payment_verified_by',
        'payment_note',
        'receipt_number',
    ];

    protected function casts(): array
    {
        return [
            'booking_type' => BookingType::class,
            'borrower_type' => BorrowerType::class,
            'status' => BookingStatus::class,
            'attendees' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'approved_at' => 'datetime',
            'fee_amount' => 'decimal:2',
            'fee_breakdown' => 'array',
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new RoomBookingVisibilityScope);

        static::creating(function (self $booking) {
            $booking->booking_number ??= NumberSequence::format('reservasi', 'RSV');
            $booking->status ??= BookingStatus::Diajukan;
            $booking->borrower_type ??= BorrowerType::Internal;

            if (Auth::check()) {
                $booking->created_by ??= Auth::id();
            }

            // Pemesan internal: identitas diambil dari akun
            if ($booking->borrower_type === BorrowerType::Internal || $booking->borrower_type === BorrowerType::Internal->value) {
                $booking->user_id ??= Auth::id();

                $pemesan = $booking->user_id ? User::find($booking->user_id) : null;

                if ($pemesan) {
                    $booking->borrower_name = $pemesan->name;
                    $booking->borrower_phone ??= $pemesan->phone;
                    $booking->borrower_email ??= $pemesan->email;
                }
            }
        });
    }

    /* ---------------------------- Activity Log ----------------------------- */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'room_id', 'loan_id', 'start_at', 'end_at', 'approved_by', 'fee_amount', 'payment_status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('Reservasi Ruang');
    }

    /* ------------------------------- Media --------------------------------- */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bukti_bayar')
            ->useDisk('public')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    /* ------------------------------ Mutator -------------------------------- */

    protected function borrowerPhone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Phone::normalize($value),
        );
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Peminjaman pelengkap. Scope visibilitas pinjaman dilepas karena
     * siapa pun yang boleh melihat reservasi boleh melihat pelengkapnya.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)
            ->withoutGlobalScope(Scopes\LoanVisibilityScope::class)
            ->withTrashed();
    }

    public function hasEquipment(): bool
    {
        return $this->loan_id !== null && $this->loan !== null
            && ! in_array($this->loan->status, [LoanStatus::Ditolak, LoanStatus::Batal], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** SK yang menjadi dasar biaya */
    public function rentalDecree(): BelongsTo
    {
        return $this->belongsTo(RentalDecree::class)->withTrashed();
    }

    public function paymentVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('acted_at');
    }

    /* -------------------------------- Scope -------------------------------- */

    /** Masih menempati jadwal ruang */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::occupyingValues());
    }

    /** Beririsan dengan rentang waktu (pembanding ketat: sesi bersambung tidak bentrok) */
    public function scopeOverlapping(
        Builder $query,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
    ): Builder {
        return $query
            ->where('start_at', '<', Carbon::parse($end))
            ->where('end_at', '>', Carbon::parse($start));
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('end_at', '>=', now());
    }

    /* ------------------------------ Aksesor -------------------------------- */

    /** Disetujui dan waktunya sudah lewat */
    public function getIsFinishedAttribute(): bool
    {
        return $this->status === BookingStatus::Disetujui && $this->end_at?->isPast();
    }

    /** Sedang berlangsung saat ini */
    public function getIsOngoingAttribute(): bool
    {
        return $this->status === BookingStatus::Disetujui
            && $this->start_at?->isPast()
            && $this->end_at?->isFuture();
    }

    /** Label status untuk tampilan, termasuk "Selesai" dan "Berlangsung" */
    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->is_finished => 'Selesai',
            $this->is_ongoing => 'Berlangsung',
            default => $this->status?->getLabel() ?? '—',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match (true) {
            $this->is_finished => 'gray',
            $this->is_ongoing => 'primary',
            default => $this->status?->getColor() ?? 'gray',
        };
    }

    /** "Sen, 6 Okt 2026 · 08.00–10.00" atau lintas hari */
    public function getScheduleLabelAttribute(): string
    {
        if (! $this->start_at || ! $this->end_at) {
            return '—';
        }

        $mulai = $this->start_at->locale('id');
        $selesai = $this->end_at->locale('id');

        if ($mulai->isSameDay($selesai)) {
            return $mulai->translatedFormat('D, j M Y').' · '
                .$mulai->format('H.i').'–'.$selesai->format('H.i');
        }

        return $mulai->translatedFormat('j M Y H.i').' – '.$selesai->translatedFormat('j M Y H.i');
    }

    public function isExternal(): bool
    {
        return $this->borrower_type === BorrowerType::Eksternal;
    }

    /** Ada tagihan sewa yang harus dibayar */
    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Lunas;
    }

    public function requiresPayment(): bool
    {
        return $this->isExternal() && (float) $this->fee_amount > 0;
    }
}
