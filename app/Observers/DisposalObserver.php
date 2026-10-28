<?php

namespace App\Observers;

use App\Enums\AssetStatus;
use App\Enums\DisposalStatus;
use App\Models\Disposal;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\Auth;

class DisposalObserver
{
    public function creating(Disposal $disposal): void
    {
        $disposal->disposal_number ??= NumberSequence::format('penghapusan', 'HPS');
        $disposal->proposed_by ??= Auth::id();
    }

    public function updated(Disposal $disposal): void
    {
        if (! $disposal->wasChanged('status')) {
            return;
        }

        match ($disposal->status) {
            DisposalStatus::Usulan,
            DisposalStatus::Pemeriksaan,
            DisposalStatus::MenungguWadir,
            DisposalStatus::Disetujui => $this->flagAssets($disposal),

            DisposalStatus::Dieksekusi => $this->retireAssets($disposal),

            DisposalStatus::Ditolak => $this->restoreAssets($disposal),

            default => null,
        };
    }

    /** Tandai aset sedang diusulkan hapus, cegah dipinjam */
    protected function flagAssets(Disposal $disposal): void
    {
        $disposal->items()->with('asset')->get()->each(function ($item) {
            $item->asset?->forceFill([
                'status' => AssetStatus::DiusulkanHapus,
                'is_loanable' => false,
            ])->saveQuietly();
        });
    }

    /**
     * Eksekusi penghapusan.
     * Aset TIDAK dihapus dari database — hanya berubah status.
     * Riwayat harus utuh untuk audit.
     */
    protected function retireAssets(Disposal $disposal): void
    {
        $isLost = $disposal->reason->value === 'hilang';

        $disposal->items()->with('asset')->get()->each(function ($item) use ($isLost) {
            $item->asset?->forceFill([
                'status' => $isLost ? AssetStatus::Hilang : AssetStatus::Dihapuskan,
                'is_loanable' => false,
            ])->saveQuietly();
        });

        if (blank($disposal->executed_at)) {
            $disposal->forceFill(['executed_at' => now()->toDateString()])->saveQuietly();
        }
    }

    protected function restoreAssets(Disposal $disposal): void
    {
        $disposal->items()->with('asset')->get()->each(function ($item) {
            $asset = $item->asset;

            if ($asset?->status === AssetStatus::DiusulkanHapus) {
                $asset->forceFill(['status' => AssetStatus::Aktif])->saveQuietly();
            }
        });
    }
}