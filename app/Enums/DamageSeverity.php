<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DamageSeverity: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Ringan = 'ringan';
    case Sedang = 'sedang';
    case Berat = 'berat';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ringan => 'Ringan — masih bisa dipakai',
            self::Sedang => 'Sedang — terganggu',
            self::Berat => 'Berat — tidak bisa dipakai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ringan => 'info',
            self::Sedang => 'warning',
            self::Berat => 'danger',
        };
    }

    public function toPriority(): PriorityLevel
    {
        return match ($this) {
            self::Ringan => PriorityLevel::Rendah,
            self::Sedang => PriorityLevel::Sedang,
            self::Berat => PriorityLevel::Tinggi,
        };
    }
}
