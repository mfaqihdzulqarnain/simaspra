<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Models\User;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanableBy: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Semua = 'semua';
    case DosenSaja = 'dosen_saja';

    public function getLabel(): string
    {
        return match ($this) {
            self::Semua => 'Dosen & Mahasiswa',
            self::DosenSaja => 'Dosen Saja',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Semua => 'success',
            self::DosenSaja => 'warning',
        };
    }

    public function allows(User $user): bool
    {
        return $this === self::Semua || ! $user->hasRole('mahasiswa');
    }
}