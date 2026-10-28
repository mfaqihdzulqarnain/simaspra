<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case BelumBayar = 'belum_bayar';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Lunas = 'lunas';

    public function getLabel(): string
    {
        return match ($this) {
            self::BelumBayar => 'Belum Bayar',
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Lunas => 'Lunas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BelumBayar => 'danger',
            self::MenungguVerifikasi => 'warning',
            self::Lunas => 'success',
        };
    }
}
