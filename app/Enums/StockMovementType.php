<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Masuk = 'masuk';
    case Keluar = 'keluar';
    case Penyesuaian = 'penyesuaian';

    public function getLabel(): string
    {
        return match ($this) {
            self::Masuk => 'Barang Masuk',
            self::Keluar => 'Barang Keluar',
            self::Penyesuaian => 'Penyesuaian Stok',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Masuk => 'success',
            self::Keluar => 'danger',
            self::Penyesuaian => 'warning',
        };
    }

    /** Pengali stok: masuk +1, keluar -1, penyesuaian ditimpa manual */
    public function multiplier(): int
    {
        return match ($this) {
            self::Masuk => 1,
            self::Keluar => -1,
            self::Penyesuaian => 0,
        };
    }
}
