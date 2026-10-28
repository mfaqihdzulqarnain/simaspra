<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Dilaporkan = 'dilaporkan';
    case Ditugaskan = 'ditugaskan';
    case Dikerjakan = 'dikerjakan';
    case Selesai = 'selesai';
    case Gagal = 'gagal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dilaporkan => 'Dilaporkan',
            self::Ditugaskan => 'Ditugaskan',
            self::Dikerjakan => 'Sedang Dikerjakan',
            self::Selesai => 'Selesai',
            self::Gagal => 'Tidak Dapat Diperbaiki',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dilaporkan => 'gray',
            self::Ditugaskan => 'warning',
            self::Dikerjakan => 'info',
            self::Selesai => 'success',
            self::Gagal => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Dilaporkan, self::Ditugaskan, self::Dikerjakan], true);
    }
}
