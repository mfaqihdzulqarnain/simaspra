<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DisposalStatus: string implements HasLabel, HasColor
{
    use EnumHelpers;

    case Usulan = 'usulan';
    case Pemeriksaan = 'pemeriksaan';
    case MenungguWadir = 'menunggu_wadir';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dieksekusi = 'dieksekusi';
    case Arsip = 'arsip';

    public function getLabel(): string
    {
        return match ($this) {
            self::Usulan => 'Usulan',
            self::Pemeriksaan => 'Pemeriksaan Fisik',
            self::MenungguWadir => 'Menunggu Persetujuan Wadir',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dieksekusi => 'Sudah Dieksekusi',
            self::Arsip => 'Arsip',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Usulan => 'gray',
            self::Pemeriksaan, self::MenungguWadir => 'warning',
            self::Disetujui => 'info',
            self::Ditolak => 'danger',
            self::Dieksekusi => 'success',
            self::Arsip => 'gray',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Usulan;
    }

    public function actorRole(): ?string
    {
        return match ($this) {
            self::Usulan => 'kabag_sarpras',
            self::Pemeriksaan => 'kabag_sarpras',
            self::MenungguWadir => 'wadir',
            self::Disetujui => 'kabag_sarpras',
            default => null,
        };
    }
}
