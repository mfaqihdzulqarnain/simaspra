<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Batal = 'batal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Batal => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Batal => 'gray',
        };
    }

    /** Menempati jadwal ruang: ikut dihitung saat cek bentrok */
    public function occupiesRoom(): bool
    {
        return in_array($this, [self::Diajukan, self::Disetujui], true);
    }

    /** @return array<string> */
    public static function occupyingValues(): array
    {
        return [self::Diajukan->value, self::Disetujui->value];
    }
}
