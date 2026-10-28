<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum RateUnit: string implements HasLabel
{
    use EnumHelpers;

    case Hari = 'hari';
    case Sesi = 'sesi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hari => 'Per hari',
            self::Sesi => 'Per sesi / paket',
        };
    }
}
