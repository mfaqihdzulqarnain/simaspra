<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanPurpose: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Praktikum = 'praktikum';
    case Perkuliahan = 'perkuliahan';
    case Penelitian = 'penelitian';
    case TugasAkhir = 'tugas_akhir';
    case Kegiatan = 'kegiatan';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Praktikum => 'Praktikum',
            self::Perkuliahan => 'Perkuliahan',
            self::Penelitian => 'Penelitian',
            self::TugasAkhir => 'Tugas Akhir / Skripsi',
            self::Kegiatan => 'Kegiatan / Acara',
            self::Lainnya => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Praktikum => 'primary',
            self::Perkuliahan => 'info',
            self::Penelitian => 'success',
            self::TugasAkhir => 'warning',
            self::Kegiatan, self::Lainnya => 'gray',
        };
    }

    /** Wajib menyebut dosen penanggung jawab */
    public function requiresSupervisor(): bool
    {
        return in_array($this, [self::Praktikum, self::Penelitian, self::TugasAkhir], true);
    }

    /** Wajib menyebut mata kuliah */
    public function requiresCourse(): bool
    {
        return in_array($this, [self::Praktikum, self::Perkuliahan], true);
    }
}