<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Aktif = 'aktif';
    case Dipinjam = 'dipinjam';
    case Perbaikan = 'perbaikan';
    case DiusulkanHapus = 'diusulkan_hapus';
    case Dihapuskan = 'dihapuskan';
    case Hilang = 'hilang';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Dipinjam => 'Sedang Dipinjam',
            self::Perbaikan => 'Dalam Perbaikan',
            self::DiusulkanHapus => 'Diusulkan Dihapus',
            self::Dihapuskan => 'Dihapuskan',
            self::Hilang => 'Hilang',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Dipinjam => 'info',
            self::Perbaikan => 'warning',
            self::DiusulkanHapus => 'warning',
            self::Dihapuskan => 'gray',
            self::Hilang => 'danger',
        };
    }

    /** Hanya status ini yang boleh dipinjam */
    public function isLoanable(): bool
    {
        return $this === self::Aktif;
    }

    /** Status akhir, tidak dihitung sebagai aset aktif */
    public function isRetired(): bool
    {
        return in_array($this, [self::Dihapuskan, self::Hilang], true);
    }
}
