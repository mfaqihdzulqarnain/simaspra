<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Scopes\LoanVisibilityScope;
use Illuminate\Support\Facades\Auth;

class ScanService
{
    /**
     * Cari aset dari kode QR atau kode aset.
     * Pencocokan tidak peka huruf besar-kecil.
     */
    public static function findAsset(string $kode): ?Asset
    {
        $kode = strtoupper(trim($kode));

        if ($kode === '') {
            return null;
        }

        return Asset::query()
            ->allUnits()
            ->with(['unit:id,name', 'room:id,name,laboratory_id', 'room.laboratory:id,name'])
            ->where(fn ($q) => $q->where('public_code', $kode)->orWhere('code', $kode))
            ->first();
    }

    /**
     * Pinjaman aktif yang memuat aset ini.
     * Scope dilepas agar laboran tetap menemukan pinjaman meski
     * dicatat oleh laboran lain di lab yang sama.
     */
    public static function activeLoanFor(Asset $asset): ?Loan
    {
        $loanIds = LoanItem::query()
            ->where('asset_id', $asset->getKey())
            ->pluck('loan_id');

        return Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->whereIn('id', $loanIds)
            ->whereIn('status', [
                LoanStatus::Disetujui->value,
                LoanStatus::Dipinjam->value,
                LoanStatus::Terlambat->value,
            ])
            ->with(['items.asset:id,code,name', 'laboratory:id,name', 'user:id,name'])
            ->orderByRaw('FIELD(status, ?, ?, ?)', [
                LoanStatus::Dipinjam->value,
                LoanStatus::Terlambat->value,
                LoanStatus::Disetujui->value,
            ])
            ->first();
    }

    /**
     * Semua pinjaman aktif yang memuat aset kolektif ini dan boleh
     * diproses pengguna. Satu kode kursi bisa berada di beberapa
     * peminjaman sekaligus.
     */
    public static function activeLoansFor(Asset $asset)
    {
        $loanIds = LoanItem::query()
            ->where('asset_id', $asset->getKey())
            ->pluck('loan_id');

        return Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->whereIn('id', $loanIds)
            ->whereIn('status', [
                LoanStatus::Disetujui->value,
                LoanStatus::Dipinjam->value,
                LoanStatus::Terlambat->value,
            ])
            ->with([
                'items' => fn ($q) => $q->where('asset_id', $asset->getKey()),
                'laboratory:id,name',
            ])
            ->orderBy('start_at')
            ->get()
            ->filter(fn (Loan $loan) => static::canHandle($loan))
            ->values();
    }

    /** Laboran hanya boleh memproses pinjaman di lab yang diampunya */
    public static function canHandle(Loan $loan): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->canSeeAllUnits()) {
            return true;
        }

        if (! $user->hasRole('laboran')) {
            return false;
        }

        return $loan->laboratory_id
            && in_array($loan->laboratory_id, $user->laboratoryIds(), true);
    }

    /** Ringkasan hasil pindai untuk ditampilkan */
    public static function scan(string $kode): array
    {
        $asset = static::findAsset($kode);

        if (! $asset) {
            return [
                'ok' => false,
                'pesan' => 'Kode "'.strtoupper(trim($kode)).'" tidak dikenali.',
            ];
        }

        // Aset kolektif: bisa dipinjam lagi sekaligus sedang dipinjam orang lain.
        // Petugas memilih sendiri: tambah ke peminjaman baru atau proses yang berjalan.
        if ($asset->isCollective()) {
            $bisaDipinjam = $asset->is_loanable && $asset->status === AssetStatus::Aktif;
            $loans = static::activeLoansFor($asset);

            if (! $bisaDipinjam && $loans->isEmpty()) {
                return [
                    'ok' => false,
                    'asset' => $asset,
                    'pesan' => $asset->code.' — aset kolektif ini tidak dipinjamkan'
                        .($asset->status !== AssetStatus::Aktif ? ' (status: '.$asset->status->getLabel().')' : '')
                        .' dan tidak ada peminjaman berjalan yang bisa Anda proses.',
                ];
            }

            return [
                'ok' => true,
                'asset' => $asset,
                'aksi' => 'kolektif',
                'bisa_pinjam' => $bisaDipinjam,
                'loans' => $loans,
            ];
        }

        $loan = static::activeLoanFor($asset);

        // Tidak sedang dipinjam: tawarkan peminjaman baru
        if (! $loan) {
            if (! $asset->is_loanable) {
                return [
                    'ok' => false,
                    'asset' => $asset,
                    'pesan' => $asset->code.' — alat ini tidak dipinjamkan.',
                ];
            }

            if ($asset->status !== AssetStatus::Aktif) {
                return [
                    'ok' => false,
                    'asset' => $asset,
                    'pesan' => $asset->code.' — status alat: '.$asset->status->getLabel()
                        .', tidak dapat dipinjam.',
                ];
            }

            return [
                'ok' => true,
                'asset' => $asset,
                'aksi' => 'baru',
            ];
        }

        if (! static::canHandle($loan)) {
            return [
                'ok' => false,
                'asset' => $asset,
                'pesan' => 'Peminjaman ini berada di luar laboratorium yang Anda tangani.',
            ];
        }

        return [
            'ok' => true,
            'asset' => $asset,
            'loan' => $loan,
            'aksi' => $loan->status === LoanStatus::Disetujui ? 'serahkan' : 'kembalikan',
        ];
    }
}
