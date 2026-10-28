<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\AssetStatus;
use App\Enums\DisposalStatus;
use App\Enums\HandoverType;
use App\Filament\Resources\Disposals\DisposalResource;
use App\Models\Approval;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\DisposalItem;
use App\Models\Handover;
use App\Models\HandoverItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DisposalService
{
    /**
     * Aset yang layak diusulkan hapus.
     * Aset yang sudah masuk usulan lain tidak ditampilkan.
     */
    public static function eligibleAssets(?int $unitId = null, ?int $exceptDisposalId = null)
    {
        $busy = DisposalItem::query()
            ->whereHas('disposal', function ($q) use ($exceptDisposalId) {
                $q->whereNotIn('status', [
                    DisposalStatus::Ditolak->value,
                    DisposalStatus::Arsip->value,
                ]);

                if ($exceptDisposalId) {
                    $q->where('id', '!=', $exceptDisposalId);
                }
            })
            ->pluck('asset_id');

        return Asset::query()
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->whereNotIn('status', [
                AssetStatus::Dihapuskan->value,
                AssetStatus::Dipinjam->value,
            ])
            ->whereNotIn('id', $busy)
            ->with(['unit', 'room', 'category'])
            ->orderBy('code')
            ->get();
    }

    /** Unit mengirim usulan — Ka. Sarpras mulai memeriksa */
    public static function submit(Disposal $disposal): void
    {
        DB::transaction(function () use ($disposal) {
            $disposal->forceFill([
                'status' => DisposalStatus::Pemeriksaan,
            ])->save();

            static::flagAssets($disposal);

            Approval::record($disposal, ApprovalAction::Setuju, 1, 'pengusul', 'Usulan dikirim');
        });
        NotifyService::toRoles(
            ['kabag_sarpras'],
            'Usulan penghapusan baru',
            $disposal->disposal_number.' dari '.$disposal->unit?->name,
            DisposalResource::getUrl('view', ['record' => $disposal]),
            'warning',
            'heroicon-o-archive-box-x-mark',
        );
    }

    /** Hasil pemeriksaan fisik, diteruskan ke Wadir */
    public static function inspect(Disposal $disposal, string $note): void
    {
        DB::transaction(function () use ($disposal, $note) {
            $disposal->forceFill([
                'status' => DisposalStatus::MenungguWadir,
                'inspected_by' => Auth::id(),
                'inspected_at' => now(),
                'inspection_note' => $note,
            ])->save();

            Approval::record($disposal, ApprovalAction::Setuju, 2, 'kabag_sarpras', $note);
        });
        NotifyService::toRoles(
            ['wadir'],
            'Penghapusan menunggu persetujuan',
            $disposal->disposal_number.' — '.$disposal->items()->count().' aset',
            DisposalResource::getUrl('view', ['record' => $disposal]),
            'warning',
            'heroicon-o-clipboard-document-check',
        );
    }

    /** Wadir menyetujui penghapusan */
    public static function approve(Disposal $disposal, ?string $note = null): void
    {
        DB::transaction(function () use ($disposal, $note) {
            $disposal->forceFill([
                'status' => DisposalStatus::Disetujui,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ])->save();

            Approval::record($disposal, ApprovalAction::Setuju, 3, 'wadir', $note);
        });
        NotifyService::toUser(
            $disposal->proposed_by,
            'Usulan penghapusan disetujui',
            $disposal->disposal_number,
            DisposalResource::getUrl('view', ['record' => $disposal]),
            'success',
            'heroicon-o-check-badge',
        );
    }

    /** Ditolak — Observer mengembalikan status aset ke Aktif */
    public static function reject(Disposal $disposal, string $reason, string $role): void
    {
        DB::transaction(function () use ($disposal, $reason, $role) {
            $disposal->forceFill([
                'status' => DisposalStatus::Ditolak,
                'rejection_reason' => $reason,
            ])->save();

            Approval::record($disposal, ApprovalAction::Tolak, $role === 'wadir' ? 3 : 2, $role, $reason);
        });
        NotifyService::toUser(
            $disposal->proposed_by,
            'Usulan penghapusan ditolak',
            $disposal->disposal_number.' — '.$reason,
            DisposalResource::getUrl('view', ['record' => $disposal]),
            'danger',
            'heroicon-o-x-circle',
        );
    }

    /**
     * Eksekusi penghapusan.
     * Observer mengubah status aset jadi Dihapuskan/Hilang.
     */
    public static function execute(Disposal $disposal, array $data): Handover
    {
        return DB::transaction(function () use ($disposal, $data) {
            $disposal->forceFill([
                'status' => DisposalStatus::Dieksekusi,
                'method' => $data['method'],
                'executed_at' => $data['executed_at'] ?? now()->toDateString(),
                'execution_note' => $data['execution_note'] ?? null,
                'revenue_amount' => $data['revenue_amount'] ?? 0,
            ])->save();

            $handover = Handover::create([
                'type' => HandoverType::Penghapusan,
                'reference_type' => $disposal->getMorphClass(),
                'reference_id' => $disposal->getKey(),
                'from_user_id' => Auth::id(),
                'to_user_id' => null,
                'unit_id' => $disposal->unit_id,
                'handover_date' => $data['executed_at'] ?? now()->toDateString(),
                'notes' => $data['execution_note'] ?? null,
            ]);

            foreach ($disposal->items()->with('asset')->get() as $item) {
                HandoverItem::create([
                    'handover_id' => $handover->getKey(),
                    'asset_id' => $item->asset_id,
                    'quantity' => max(1, (int) ($item->asset?->quantity ?? 1)),
                    'condition' => $item->condition,
                ]);
            }

            return $handover;
        });
    }

    /** Arsipkan setelah dokumen fisik lengkap */
    public static function archive(Disposal $disposal): void
    {
        $disposal->forceFill(['status' => DisposalStatus::Arsip])->save();
    }

    /** Tandai aset sedang diusulkan hapus */
    protected static function flagAssets(Disposal $disposal): void
    {
        $disposal->items()->with('asset')->get()->each(function (DisposalItem $item) {
            $item->asset?->forceFill([
                'status' => AssetStatus::DiusulkanHapus,
                'is_loanable' => false,
            ])->saveQuietly();
        });
    }
}
