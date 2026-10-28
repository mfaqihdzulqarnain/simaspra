<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderType: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case Preventif = 'preventif';
    case Korektif = 'korektif';
    case Kalibrasi = 'kalibrasi';
    case Instalasi = 'instalasi';
    case Pemindahan = 'pemindahan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preventif => 'Pemeliharaan Preventif',
            self::Korektif => 'Perbaikan Korektif',
            self::Kalibrasi => 'Kalibrasi',
            self::Instalasi => 'Instalasi / Pemasangan',
            self::Pemindahan => 'Pemindahan Aset',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preventif => 'info',
            self::Korektif => 'warning',
            self::Kalibrasi => 'primary',
            self::Instalasi => 'success',
            self::Pemindahan => 'gray',
        };
    }
}
