<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OpnameItemStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Ditemukan = 'ditemukan';
    case TidakDitemukan = 'tidak_ditemukan';
    case BedaLokasi = 'beda_lokasi';
    case BedaKondisi = 'beda_kondisi';
    case BedaJumlah = 'beda_jumlah';
    case TidakTercatat = 'tidak_tercatat';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ditemukan => 'Ditemukan Sesuai',
            self::TidakDitemukan => 'Tidak Ditemukan',
            self::BedaLokasi => 'Beda Lokasi',
            self::BedaKondisi => 'Beda Kondisi',
            self::BedaJumlah => 'Beda Jumlah',
            self::TidakTercatat => 'Ada tapi Belum Tercatat',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ditemukan => 'success',
            self::TidakDitemukan => 'danger',
            self::BedaLokasi, self::BedaKondisi, self::BedaJumlah => 'warning',
            self::TidakTercatat => 'info',
        };
    }

    /** Selisih yang wajib ditindaklanjuti di berita acara */
    public function isDiscrepancy(): bool
    {
        return $this !== self::Ditemukan;
    }
}
