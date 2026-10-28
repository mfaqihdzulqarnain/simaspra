<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dipinjam = 'dipinjam';
    case Dikembalikan = 'dikembalikan';
    case Terlambat = 'terlambat';
    case Batal = 'batal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dipinjam => 'Sedang Dipinjam',
            self::Dikembalikan => 'Sudah Dikembalikan',
            self::Terlambat => 'Terlambat',
            self::Batal => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Disetujui => 'info',
            self::Ditolak => 'danger',
            self::Dipinjam => 'primary',
            self::Dikembalikan => 'success',
            self::Terlambat => 'danger',
            self::Batal => 'gray',
        };
    }

    /** Aset masih di tangan peminjam */
    public function isOngoing(): bool
    {
        return in_array($this, [self::Dipinjam, self::Terlambat], true);
    }
}
