<?php

namespace App\Services;

use App\Enums\AcquisitionSource;
use App\Enums\HandoverType;
use App\Enums\RequestStatus;
use App\Models\Asset;
use App\Models\Handover;
use App\Models\HandoverItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DistributionService
{
    /**
     * Salurkan pengajuan yang sudah disetujui.
     *
     * $rows: array per item pengajuan, berisi:
     *   item_id, quantity, room_id, vendor_id, contract_id,
     *   acquisition_date, acquisition_value, warranty_until, serial_numbers[]
     */
    public static function distribute(
        ProcurementRequest $request,
        array $rows,
        ?int $toUserId = null,
        ?string $handoverDate = null,
        ?string $notes = null,
    ): Handover {
        return DB::transaction(function () use ($request, $rows, $toUserId, $handoverDate, $notes) {

            $assets = new Collection;

            foreach ($rows as $row) {
                $item = ProcurementRequestItem::query()
                    ->whereKey($row['item_id'])
                    ->where('procurement_request_id', $request->getKey())
                    ->firstOrFail();

                $quantity = (int) ($row['quantity'] ?? $item->quantity);

                if ($quantity < 1) {
                    continue;
                }

                $serials = $row['serial_numbers'] ?? [];

                for ($i = 0; $i < $quantity; $i++) {
                    $assets->push(static::createAsset($request, $item, $row, $serials[$i] ?? null));
                }

                // Catat jumlah yang benar-benar disalurkan
                $item->forceFill(['approved_quantity' => $quantity])->save();
            }

            $handover = Handover::create([
                'type' => HandoverType::Penyaluran,
                'reference_type' => $request->getMorphClass(),
                'reference_id' => $request->getKey(),
                'from_user_id' => Auth::id(),
                'to_user_id' => $toUserId ?? $request->requested_by,
                'unit_id' => $request->unit_id,
                'handover_date' => $handoverDate ?? now()->toDateString(),
                'notes' => $notes,
            ]);

            foreach ($assets as $asset) {
                HandoverItem::create([
                    'handover_id' => $handover->getKey(),
                    'asset_id' => $asset->getKey(),
                    'quantity' => max(1, (int) $asset->quantity),
                    'condition' => $asset->condition,
                ]);
            }

            $request->forceFill([
                'status' => RequestStatus::Selesai,
            ])->save();

            return $handover;
        });
    }

    protected static function createAsset(
        ProcurementRequest $request,
        ProcurementRequestItem $item,
        array $row,
        ?string $serial,
    ): Asset {
        // Kode, uuid, dan public_code dibuat otomatis oleh AssetObserver
        return Asset::create([
            'name' => $item->name,
            'asset_category_id' => $item->asset_category_id,
            'unit_id' => $request->unit_id,
            'room_id' => $row['room_id'] ?? null,
            'vendor_id' => $row['vendor_id'] ?? null,
            'contract_id' => $row['contract_id'] ?? null,
            'procurement_request_item_id' => $item->getKey(),
            'serial_number' => $serial,
            'year_acquired' => (int) ($row['acquisition_date'] ?? null
                ? date('Y', strtotime($row['acquisition_date']))
                : now()->format('Y')),
            'acquisition_date' => $row['acquisition_date'] ?? now()->toDateString(),
            'acquisition_source' => AcquisitionSource::Pembelian,
            'acquisition_value' => $row['acquisition_value'] ?? $item->estimated_unit_price,
            'warranty_until' => $row['warranty_until'] ?? null,
            'notes' => 'Hasil pengajuan ' . $request->request_number,
        ]);
    }
}