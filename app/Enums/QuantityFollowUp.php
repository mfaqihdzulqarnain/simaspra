<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Tindak lanjut selisih jumlah aset kolektif saat stock opname ditutup */
enum QuantityFollowUp: string implements HasLabel
{
    use EnumHelpers;

    case Sesuaikan = 'sesuaikan';
    case Laporkan = 'laporkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sesuaikan => 'Sesuaikan jumlah aset',
            self::Laporkan => 'Buat laporan kehilangan',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Sesuaikan => 'Jumlah unit aset diubah menjadi jumlah yang ditemukan. Bila berkurang, nilai perolehan ikut disesuaikan sebanding. Bila tidak ada satu pun yang ditemukan, status aset menjadi Hilang.',
            self::Laporkan => 'Jumlah aset tidak diubah. Sistem membuat laporan kerusakan berisi unit yang tidak ditemukan untuk ditelusuri lebih dulu.',
        };
    }
}
