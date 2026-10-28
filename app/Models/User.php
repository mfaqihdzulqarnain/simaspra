<?php

namespace App\Models;

use App\Support\Phone;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
// use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'unit_id',
        'name',
        'nip',
        'position',
        'phone',
        'email',
        'password',
        'is_active',
        'nim',
        'entry_year',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'entry_year' => 'integer',
        ];
    }

    /* ----------------------------- Akses Panel ----------------------------- */

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->roles()->exists();
    }

        /** Role yang melihat data seluruh kampus */
        public function canSeeAllUnits(): bool
        {
            return $this->hasAnyRole([
                'super_admin',
                'pimpinan',
                'wadir',
                'kabag_sarpras',
            ]);
        }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Unit yang dipimpin user ini */
    public function ledUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'head_user_id');
    }

    public function managedRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'pic_user_id');
    }

    public function procurementRequests(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class, 'requested_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function assignedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'assigned_to');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /* ------------------------------ Aksesor -------------------------------- */

    public function getDisplayNameAttribute(): string
    {
        return $this->nip
            ? "{$this->name} ({$this->nip})"
            : $this->name;
    }

    /** Cache daftar lab per permintaan, dipakai scope berkali-kali */
    protected ?array $cachedLaboratoryIds = null;

    /* ------------------------- Laboratorium & akademik ------------------------ */

    public function laboratories(): BelongsToMany
    {
        return $this->belongsToMany(Laboratory::class, 'laboratory_user')->withTimestamps();
    }

    public function laboratoryIds(): array
    {
        return $this->cachedLaboratoryIds ??= $this->laboratories()
            ->pluck('laboratories.id')
            ->all();
    }

    public function isStudent(): bool
    {
        return $this->hasRole('mahasiswa');
    }

    /** NIM untuk mahasiswa, NIP/NIDN untuk dosen dan tendik */
    public function getIdentityNumberAttribute(): ?string
    {
        return $this->nim ?: $this->nip;
    }

    /** Nomor HP selalu disimpan dalam format 62… */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Phone::normalize($value),
        );
    }

    public function isLecturer(): bool
    {
        return $this->hasAnyRole(['dosen', 'kaprodi']);
    }

    public function isLabStaff(): bool
    {
        return $this->hasRole('laboran');
    }

    /**
     * Kembalikan kata sandi ke nomor identitas (NIM atau NIP).
     * Cast 'hashed' pada kolom password yang melakukan hashing.
     */
    public function resetPasswordToIdentity(): bool
    {
        $identitas = $this->identity_number;

        if (blank($identitas)) {
            return false;
        }

        $this->forceFill(['password' => $identitas])->save();

        return true;
    }
}
