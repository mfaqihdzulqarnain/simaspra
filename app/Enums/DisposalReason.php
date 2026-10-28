<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DisposalReason: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case RusakBerat = 'rusak_berat';
    case Hilang = 'hilang';
    case UsangTeknologi = 'usang_teknologi';
    case TidakEkonomis = 'tidak_ekonomis';
    case Kadaluarsa = 'kadaluarsa';

    public function getLabel(): string
    {
        return match ($this) {
            self::RusakBerat => 'Rusak Berat',
            self::Hilang => 'Hilang',
            self::UsangTeknologi => 'Usang secara Teknologi',
            self::TidakEkonomis => 'Tidak Ekonomis Diperbaiki',
            self::Kadaluarsa => 'Kedaluwarsa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::RusakBerat, self::Hilang => 'danger',
            self::UsangTeknologi, self::TidakEkonomis => 'warning',
            self::Kadaluarsa => 'gray',
        };
    }
}
