<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BorrowerType: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Internal = 'internal';
    case Eksternal = 'eksternal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Internal => 'Internal Kampus',
            self::Eksternal => 'Eksternal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Internal => 'primary',
            self::Eksternal => 'warning',
        };
    }

    /** Eksternal wajib jaminan */
    public function requiresDeposit(): bool
    {
        return $this === self::Eksternal;
    }
}
