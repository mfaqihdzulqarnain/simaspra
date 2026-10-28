<?php

namespace App\Observers;

use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\NumberSequence;
use App\Services\ConsumableLoanService;
use App\Services\NotifyService;
use Illuminate\Support\Facades\Auth;

class LoanObserver
{
    /**
     * Matikan notifikasi "permohonan baru" untuk peminjaman yang dibuat sistem,
     * misalnya pelengkap reservasi ruang. Penyetuju sudah diberi tahu lewat
     * notifikasi reservasinya.
     */
    public static bool $muteCreatedNotification = false;

    public function creating(Loan $loan): void
    {
        $loan->loan_number ??= NumberSequence::format('peminjaman', 'PJM');

        // Peminjam internal: lengkapi identitas dari akun
        if (Auth::check() && blank($loan->borrower_name)) {
            $loan->user_id ??= Auth::id();
            $loan->borrower_name = Auth::user()->name;
            $loan->borrower_phone ??= Auth::user()->phone ?? '-';
        }
    }

    public function updated(Loan $loan): void
    {
        if (! $loan->wasChanged('status')) {
            return;
        }

        match ($loan->status) {
            LoanStatus::Dipinjam => $this->markAssetsBorrowed($loan),
            LoanStatus::Dikembalikan => $this->releaseAssets($loan),
            LoanStatus::Ditolak, LoanStatus::Batal => $this->cancel($loan),
            default => null,
        };
    }

    /** Ditolak atau dibatalkan: lepas aset dan cadangan stok alat massal */
    protected function cancel(Loan $loan): void
    {
        $this->releaseAssets($loan);

        ConsumableLoanService::release($loan);
    }

    /**
     * Aset tunggal berstatus Dipinjam selama di tangan peminjam.
     * Aset kolektif tidak: sisa unitnya tetap bisa dipinjam orang lain,
     * dan jumlah yang keluar dilacak lewat baris peminjaman.
     */
    protected function markAssetsBorrowed(Loan $loan): void
    {
        $loan->items()->with('asset')->get()->each(function ($item) {
            $asset = $item->asset;

            if (! $asset || $asset->isCollective()) {
                return;
            }

            $asset->forceFill([
                'status' => AssetStatus::Dipinjam,
            ])->saveQuietly();
        });
    }

    protected function releaseAssets(Loan $loan): void
    {
        $returnedAt = $loan->returned_at ?? now();

        $loan->items()->with('asset')->get()->each(function ($item) use ($loan, $returnedAt) {
            $asset = $item->asset;

            if (! $asset) {
                return;
            }

            // Hanya lepaskan aset yang memang tercatat sedang dipinjam
            if ($asset->status === AssetStatus::Dipinjam) {
                $asset->forceFill([
                    'status' => AssetStatus::Aktif,
                    'condition' => $item->condition_after ?? $asset->condition,
                ])->saveQuietly();
            }

            if ($loan->status === LoanStatus::Dikembalikan && blank($item->returned_at)) {
                $item->forceFill(['returned_at' => $returnedAt])->saveQuietly();
            }
        });

        if ($loan->status === LoanStatus::Dikembalikan && blank($loan->returned_at)) {
            $loan->forceFill(['returned_at' => $returnedAt])->saveQuietly();
        }
    }

    public function created(Loan $loan): void
    {
        if (static::$muteCreatedNotification) {
            return;
        }

        NotifyService::toLabStaff(
            $loan->laboratory_id,
            'Permohonan peminjaman baru',
            $loan->loan_number . ' — ' . $loan->borrower_name
                . ' · ' . $loan->start_at->format('d/m/Y H:i')
                . ($loan->laboratory ? ' · ' . $loan->laboratory->name : ''),
            \App\Filament\Resources\Loans\LoanResource::getUrl('view', ['record' => $loan]),
            'warning',
            'heroicon-o-arrows-right-left',
        );
    }
}
