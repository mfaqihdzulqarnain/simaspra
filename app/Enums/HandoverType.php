<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HandoverType: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Penyaluran = 'penyaluran';
    case Peminjaman = 'peminjaman';
    case Pengembalian = 'pengembalian';
    case Penghapusan = 'penghapusan';
    case Mutasi = 'mutasi';

    /** Prefix nomor dokumen per jenis */
    public function numberPrefix(): string
    {
        return match ($this) {
            self::Penyaluran => 'BAST',
            self::Peminjaman => 'BAPP',
            self::Pengembalian => 'BAPK',
            self::Penghapusan => 'BAPB',
            self::Mutasi => 'BAM',
        };
    }

    /** Tipe deret di tabel number_sequences */
    public function sequenceType(): string
    {
        return 'ba_' . $this->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Penyaluran => 'Penyaluran Aset',
            self::Peminjaman => 'Serah Terima Peminjaman',
            self::Pengembalian => 'Pengembalian Pinjaman',
            self::Penghapusan => 'Penghapusan Aset',
            self::Mutasi => 'Mutasi Aset',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Penyaluran => 'success',
            self::Peminjaman => 'info',
            self::Pengembalian => 'primary',
            self::Penghapusan => 'danger',
            self::Mutasi => 'warning',
        };
    }

    /** Judul dokumen cetak */
    public function documentTitle(): string
    {
        return match ($this) {
            self::Penyaluran => 'BERITA ACARA SERAH TERIMA BARANG',
            self::Peminjaman => 'BERITA ACARA PEMINJAMAN BARANG',
            self::Pengembalian => 'BERITA ACARA PENGEMBALIAN BARANG',
            self::Penghapusan => 'BERITA ACARA PENGHAPUSAN BARANG',
            self::Mutasi => 'BERITA ACARA MUTASI BARANG',
        };
    }
}