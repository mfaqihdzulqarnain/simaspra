<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Models\Building;
use App\Models\Concerns\HasPublicCode;
use App\Models\Consumable;
use App\Models\Laboratory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory, HasPublicCode;

    protected $fillable = [
        'building_id',
        'unit_id',
        'code',
        'name',
        'floor',
        'area_m2',
        'capacity',
        'pic_user_id',
        'is_active',
        'laboratory_id',
        'is_bookable',
        'booking_types',
        'booking_setup_minutes',
        'booking_cleanup_minutes',
    ];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'area_m2' => 'decimal:2',
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'is_bookable' => 'boolean',
            'booking_types' => 'array',
            'booking_setup_minutes' => 'integer',
            'booking_cleanup_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $room) {
            $room->public_code ??= self::generatePublicCode();
        });
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function storedConsumables(): HasMany
    {
        return $this->hasMany(Consumable::class, 'storage_room_id');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Aktif dan dibuka untuk Reservasi Ruang */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_bookable', true);
    }

    /* ----------------------------- Reservasi ------------------------------ */

    /**
     * Jenis kegiatan yang boleh memakai ruang ini.
     * Kosong berarti semua jenis diizinkan.
     *
     * @return array<BookingType>
     */
    public function allowedBookingTypes(): array
    {
        $izin = collect($this->booking_types ?? [])
            ->map(fn ($v) => BookingType::tryFrom((string) $v))
            ->filter()
            ->values()
            ->all();

        return empty($izin) ? BookingType::cases() : $izin;
    }

    /**
     * Halaman publik ruang (jadwal dan identitas ruang).
     * Menggantikan bawaan HasPublicCode yang mengarah ke halaman aset.
     */
    public function getPublicUrlAttribute(): string
    {
        return route('room.public', $this->public_code);
    }

    public function qrPayload(): string
    {
        return $this->public_url;
    }

    /** Waktu persiapan sebelum acara, dalam menit */
    public function setupMinutes(): int
    {
        return max(0, (int) $this->booking_setup_minutes);
    }

    /** Waktu beres-beres sesudah acara, dalam menit */
    public function cleanupMinutes(): int
    {
        return max(0, (int) $this->booking_cleanup_minutes);
    }

    /** "1 hari", "4 jam", "1 hari 2 jam", "30 menit", atau "Tanpa jeda" */
    public static function formatDuration(int $menit): string
    {
        if ($menit <= 0) {
            return 'Tanpa jeda';
        }

        $hari = intdiv($menit, 1440);
        $jam = intdiv($menit % 1440, 60);
        $sisa = $menit % 60;

        return collect([
            $hari ? $hari.' hari' : null,
            $jam ? $jam.' jam' : null,
            $sisa ? $sisa.' menit' : null,
        ])->filter()->implode(' ');
    }

    public function allowsBookingType(BookingType|string|null $type): bool
    {
        $type = $type instanceof BookingType ? $type : BookingType::tryFrom((string) $type);

        return $type !== null && in_array($type, $this->allowedBookingTypes(), true);
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getFullNameAttribute(): string
    {
        return trim(($this->building?->name ?? '').' — '.$this->name.' ('.$this->code.')');
    }

    /* ------------------------------ Laboratory -------------------------------- */
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }
}