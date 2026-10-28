<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
// use App\Enums\ConsumableType;

enum ConsumableType: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case HabisPakai = 'habis_pakai';
    case AlatMassal = 'alat_massal';

    public function getLabel(): string
    {
        return match ($this) {
            self::HabisPakai => 'Habis Pakai',
            self::AlatMassal => 'Alat Massal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::HabisPakai => 'gray',
            self::AlatMassal => 'info',
        };
    }

    /** Alat massal keluar lalu kembali; habis pakai berkurang permanen */
    public function isLoanable(): bool
    {
        return $this === self::AlatMassal;
    }
}