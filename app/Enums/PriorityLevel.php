<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PriorityLevel: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';
    case Mendesak = 'mendesak';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Sedang => 'Sedang',
            self::Tinggi => 'Tinggi',
            self::Mendesak => 'Mendesak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rendah => 'gray',
            self::Sedang => 'info',
            self::Tinggi => 'warning',
            self::Mendesak => 'danger',
        };
    }

    /** Untuk sorting: makin besar makin didahulukan */
    public function weight(): int
    {
        return match ($this) {
            self::Rendah => 1,
            self::Sedang => 2,
            self::Tinggi => 3,
            self::Mendesak => 4,
        };
    }
}
