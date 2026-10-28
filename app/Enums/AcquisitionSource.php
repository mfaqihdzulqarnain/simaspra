<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AcquisitionSource: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Pembelian = 'pembelian';
    case Hibah = 'hibah';
    case Bantuan = 'bantuan';
    case ProduksiSendiri = 'produksi_sendiri';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pembelian => 'Pembelian',
            self::Hibah => 'Hibah',
            self::Bantuan => 'Bantuan/Donasi',
            self::ProduksiSendiri => 'Produksi Sendiri',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pembelian => 'primary',
            self::Hibah => 'success',
            self::Bantuan => 'info',
            self::ProduksiSendiri => 'gray',
        };
    }
}
