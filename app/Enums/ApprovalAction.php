<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApprovalAction: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Setuju = 'setuju';
    case Tolak = 'tolak';
    case Revisi = 'revisi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Setuju => 'Disetujui',
            self::Tolak => 'Ditolak',
            self::Revisi => 'Dikembalikan untuk Revisi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Setuju => 'success',
            self::Tolak => 'danger',
            self::Revisi => 'warning',
        };
    }

    /** Tolak dan revisi wajib isi catatan */
    public function requiresNote(): bool
    {
        return $this !== self::Setuju;
    }
}
