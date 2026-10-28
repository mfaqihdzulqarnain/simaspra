<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\PriorityLevel;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\DamageReport;
use App\Models\MaintenanceSchedule;
// use Illuminate\Support\Facades\Auth;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\WorkOrders\WorkOrderResource;

class MaintenanceService
{
    /** Ubah laporan kerusakan menjadi perintah kerja */
    public static function createFromReport(DamageReport $report, array $data): WorkOrder
    {
        return DB::transaction(function () use ($report, $data) {
            $order = WorkOrder::create([
                'asset_id' => $report->asset_id,
                'damage_report_id' => $report->getKey(),
                'type' => WorkOrderType::Korektif,
                'priority' => $data['priority'] ?? $report->severity->toPriority(),
                'description' => $data['description'] ?? $report->description,
                'assigned_to' => $data['assigned_to'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status' => filled($data['assigned_to'] ?? null)
                    ? WorkOrderStatus::Ditugaskan
                    : WorkOrderStatus::Dilaporkan,
            ]);
            if (filled($data['assigned_to'] ?? null)) {
                NotifyService::toUser(
                    $data['assigned_to'],
                    'Perintah kerja baru untuk Anda',
                    $order->number . ' — ' . ($order->asset?->code ?? ''),
                    WorkOrderResource::getUrl('view', ['record' => $order]),
                    'info',
                    'heroicon-o-wrench-screwdriver',
                );
            }
            // Observer WorkOrder menandai laporan sudah diproses
            return $order;
        });
    }

    /** Buat work order dari jadwal preventif yang jatuh tempo */
    public static function createFromSchedule(MaintenanceSchedule $schedule, int $assetId): WorkOrder
    {
        return WorkOrder::create([
            'asset_id' => $assetId,
            'maintenance_schedule_id' => $schedule->getKey(),
            'type' => $schedule->type,
            'priority' => PriorityLevel::Sedang,
            'description' => $schedule->name,
            'assigned_to' => $schedule->assigned_to,
            'scheduled_at' => $schedule->next_due_at,
            'status' => $schedule->assigned_to
                ? WorkOrderStatus::Ditugaskan
                : WorkOrderStatus::Dilaporkan,
        ]);
    }

    /**
     * Bangkitkan work order untuk semua jadwal jatuh tempo.
     * Dipanggil manual lewat tombol, atau terjadwal lewat scheduler.
     */
    public static function generateDueWorkOrders(int $daysAhead = 7): int
    {
        $created = 0;

        MaintenanceSchedule::query()
            ->due($daysAhead)
            ->with(['asset', 'category'])
            ->get()
            ->each(function (MaintenanceSchedule $schedule) use (&$created) {
                $assetIds = $schedule->coveredAssets()->pluck('id');

                foreach ($assetIds as $assetId) {
                    // Lewati bila sudah ada work order terbuka untuk jadwal & aset ini
                    $exists = WorkOrder::query()
                        ->where('maintenance_schedule_id', $schedule->getKey())
                        ->where('asset_id', $assetId)
                        ->open()
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    static::createFromSchedule($schedule, $assetId);
                    $created++;
                }
            });

        return $created;
    }

    /** Tugaskan pelaksana */
    public static function assign(WorkOrder $order, ?int $userId, ?int $vendorId = null, ?string $scheduledAt = null): void
    {
        $order->forceFill([
            'assigned_to' => $userId,
            'vendor_id' => $vendorId,
            'scheduled_at' => $scheduledAt,
            'status' => WorkOrderStatus::Ditugaskan,
        ])->save();

        NotifyService::toUser(
            $userId,
            'Perintah kerja baru untuk Anda',
            $order->number . ' — ' . ($order->asset?->code ?? '') . ' · ' . $order->type->getLabel(),
            WorkOrderResource::getUrl('view', ['record' => $order]),
            'info',
            'heroicon-o-wrench-screwdriver',
        );
    }

    /** Selesai — Observer mengembalikan status aset dan menggeser jadwal */
    public static function finish(WorkOrder $order, array $data): void
    {
        $order->forceFill([
            'status' => WorkOrderStatus::Selesai,
            'finished_at' => now(),
            'cost' => $data['cost'] ?? 0,
            'condition_after' => $data['condition_after'] ?? AssetCondition::Baik,
            'result_note' => $data['result_note'] ?? null,
        ])->save();
    }

        /**
     * Gagal — Observer menandai aset tunggal diusulkan hapus.
     * Aset kolektif: unit yang rusak total atau hilang dikurangkan dari jumlahnya.
     */
    public static function fail(WorkOrder $order, string $note, float $cost = 0, int $unitsLost = 0): void
    {
        DB::transaction(function () use ($order, $note, $cost, $unitsLost) {
            $order->forceFill([
                'status' => WorkOrderStatus::Gagal,
                'finished_at' => now(),
                'cost' => $cost,
                'result_note' => $note.($unitsLost > 0 ? ' Jumlah aset dikurangi '.$unitsLost.' unit.' : ''),
            ])->save();

            $asset = $order->asset;

            if ($unitsLost > 0 && $asset?->isCollective()) {
                $sisa = (int) $asset->quantity - $unitsLost;

                // Semua unit hilang: aset berstatus Hilang, jumlah dan nilai dibiarkan sebagai arsip
                $asset->forceFill($sisa > 0
                    ? [
                        'quantity' => $sisa,
                        'acquisition_value' => round($asset->unitValue() * $sisa, 2),
                    ]
                    : ['status' => AssetStatus::Hilang]
                )->save();
            }
        });
    }

    /**
     * Mulai dikerjakan — Observer mengubah status aset jadi Perbaikan.
     * Harus sudah ada pelaksana: teknisi internal atau vendor.
     */
    public static function start(WorkOrder $order): void
    {
        if (blank($order->assigned_to) && blank($order->vendor_id)) {
            throw new \RuntimeException('Perintah kerja harus ditugaskan ke teknisi atau vendor sebelum dikerjakan.');
        }

        $order->forceFill([
            'status' => WorkOrderStatus::Dikerjakan,
            'started_at' => now(),
        ])->save();
    }
}