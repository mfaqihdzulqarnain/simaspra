<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DisposalMethod: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Musnah = 'musnah';
    case Hibah = 'hibah';
    case Jual = 'jual';
    case TukarGuling = 'tukar_guling';

    public function getLabel(): string
    {
        return match ($this) {
            self::Musnah => 'Dimusnahkan',
            self::Hibah => 'Dihibahkan',
            self::Jual => 'Dijual/Lelang',
            self::TukarGuling => 'Tukar Guling',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Musnah => 'danger',
            self::Hibah => 'success',
            self::Jual => 'info',
            self::TukarGuling => 'warning',
        };
    }

    /** Metode yang menghasilkan pemasukan, wajib catat nilai */
    public function generatesRevenue(): bool
    {
        return in_array($this, [self::Jual, self::TukarGuling], true);
    }
}
