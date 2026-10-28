<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UnitType: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Prodi = 'prodi';
    case Lembaga = 'lembaga';
    case Bagian = 'bagian';
    case Upt = 'upt';
    case Unit = 'unit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prodi => 'Program Studi',
            self::Lembaga => 'Lembaga',
            self::Bagian => 'Bagian',
            self::Upt => 'UPT',
            self::Unit => 'Unit Kerja',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prodi => 'primary',
            self::Lembaga => 'info',
            self::Bagian => 'warning',
            self::Upt => 'success',
            self::Unit => 'gray',
        };
    }
}
