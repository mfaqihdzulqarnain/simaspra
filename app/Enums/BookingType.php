<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingType: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Perkuliahan = 'perkuliahan';
    case Rapat = 'rapat';
    case Acara = 'acara';

    public function getLabel(): string
    {
        return match ($this) {
            self::Perkuliahan => 'Perkuliahan',
            self::Rapat => 'Rapat',
            self::Acara => 'Acara',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Perkuliahan => 'info',
            self::Rapat => 'primary',
            self::Acara => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Perkuliahan => 'Kelas pengganti atau tambahan',
            self::Rapat => 'Rapat dan pertemuan internal',
            self::Acara => 'Seminar, wisuda, kegiatan, atau sewa pihak luar',
        };
    }
}
