<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OpnameStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Draf = 'draf';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Berjalan => 'Sedang Berjalan',
            self::Selesai => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Berjalan => 'info',
            self::Selesai => 'success',
        };
    }

    /** Hanya saat berjalan, scan QR boleh menambah data */
    public function acceptsScan(): bool
    {
        return $this === self::Berjalan;
    }
}
