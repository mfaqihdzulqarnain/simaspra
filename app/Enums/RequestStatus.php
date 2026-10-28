<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case VerifikasiSarpras = 'verifikasi_sarpras';
    case Revisi = 'revisi';
    case MenungguWadir = 'menunggu_wadir';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Disalurkan = 'disalurkan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::VerifikasiSarpras => 'Verifikasi Sarpras',
            self::Revisi => 'Perlu Revisi',
            self::MenungguWadir => 'Menunggu Persetujuan Wadir',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Disalurkan => 'Sudah Disalurkan',
            self::Selesai => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Diajukan, self::VerifikasiSarpras, self::MenungguWadir => 'warning',
            self::Revisi => 'warning',
            self::Disetujui, self::Disalurkan, self::Selesai => 'success',
            self::Ditolak => 'danger',
        };
    }

    /** Boleh diedit pengaju */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draf, self::Revisi], true);
    }

    /** Status akhir, tidak ada aksi lanjutan */
    public function isFinal(): bool
    {
        return in_array($this, [self::Ditolak, self::Selesai], true);
    }

    /** Role yang wajib bertindak pada status ini */
    public function actorRole(): ?string
    {
        return match ($this) {
            self::Diajukan, self::VerifikasiSarpras => 'kabag_sarpras',
            self::MenungguWadir => 'wadir',
            self::Disetujui => 'kabag_sarpras',
            default => null,
        };
    }
}
