<?php

namespace App\Services;

use App\Enums\HandoverType;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Handover;
use App\Models\HandoverItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MutationService
{
    /**
     * Pindahkan sekumpulan aset.
     * Model AssetMovement yang memperbarui posisi aset lewat booted().
     */
    public static function move(
        Collection $assets,
        ?int $toUnitId,
        ?int $toRoomId,
        string $reason,
        ?string $movedAt = null,
        ?int $toUserId = null,
        bool $createHandover = true,
    ): ?Handover {
        return DB::transaction(function () use (
            $assets, $toUnitId, $toRoomId, $reason, $movedAt, $toUserId, $createHandover
        ) {
            $movedAt ??= now()->toDateString();
            $moved = [];

            foreach ($assets as $asset) {
                $tujuanUnit = $toUnitId ?: $asset->unit_id;
                $tujuanRuang = $toRoomId ?: $asset->room_id;

                // Lewati aset yang posisinya tidak berubah
                if ($tujuanUnit === $asset->unit_id && $tujuanRuang === $asset->room_id) {
                    continue;
                }

                AssetMovement::create([
                    'asset_id' => $asset->getKey(),
                    'from_unit_id' => $asset->unit_id,
                    'to_unit_id' => $tujuanUnit,
                    'from_room_id' => $asset->room_id,
                    'to_room_id' => $tujuanRuang,
                    'moved_at' => $movedAt,
                    'reason' => $reason,
                ]);

                $moved[] = $asset;
            }

            if (! $createHandover || empty($moved)) {
                return null;
            }

            $handover = Handover::create([
                'type' => HandoverType::Mutasi,
                'from_user_id' => Auth::id(),
                'to_user_id' => $toUserId,
                'unit_id' => $toUnitId,
                'handover_date' => $movedAt,
                'notes' => $reason,
            ]);

            foreach ($moved as $asset) {
                HandoverItem::create([
                    'handover_id' => $handover->getKey(),
                    'asset_id' => $asset->getKey(),
                    'quantity' => max(1, (int) $asset->quantity),
                    'condition' => $asset->condition,
                ]);
            }

            // Kaitkan mutasi dengan nomor berita acara
            AssetMovement::query()
                ->whereIn('asset_id', collect($moved)->pluck('id'))
                ->where('moved_at', $movedAt)
                ->whereNull('document_number')
                ->update(['document_number' => $handover->number]);

            return $handover;
        });
    }

    /** Aset yang boleh dimutasi — tidak sedang dipinjam atau diusulkan hapus */
    public static function isMovable(Asset $asset): bool
    {
        return in_array($asset->status->value, ['aktif', 'perbaikan'], true);
    }
}