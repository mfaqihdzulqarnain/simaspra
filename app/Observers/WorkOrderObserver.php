<?php

namespace App\Observers;

use App\Enums\AssetStatus;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;

class WorkOrderObserver
{
    public function created(WorkOrder $order): void
    {
        // Laporan kerusakan yang jadi work order dianggap sudah diproses
        if ($order->damage_report_id) {
            $order->damageReport?->forceFill(['is_processed' => true])->save();
        }
    }

    public function updated(WorkOrder $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $asset = $order->asset;

        if (! $asset) {
            return;
        }

        // Aset kolektif: perbaikan menyangkut sebagian unit saja, jadi status dan
        // kondisi seluruh aset tidak diubah. Unit yang hilang dikurangi lewat
        // MaintenanceService::fail(). Jadwal preventif tetap digeser.
        if ($asset->isCollective()) {
            if ($order->status === WorkOrderStatus::Selesai) {
                $order->schedule?->markDone(($order->finished_at ?? now())->toDateString());
            }

            return;
        }

        match ($order->status) {
            WorkOrderStatus::Dikerjakan => $this->toRepair($order),
            WorkOrderStatus::Selesai => $this->finish($order),
            WorkOrderStatus::Gagal => $this->fail($order),
            default => null,
        };
    }

    protected function toRepair(WorkOrder $order): void
    {
        $order->asset->forceFill([
            'status' => AssetStatus::Perbaikan,
        ])->saveQuietly();

        if (blank($order->started_at)) {
            $order->forceFill(['started_at' => now()])->saveQuietly();
        }
    }

    protected function finish(WorkOrder $order): void
    {
        $asset = $order->asset;

        $asset->forceFill([
            'status' => AssetStatus::Aktif,
            'condition' => $order->condition_after ?? $asset->condition,
        ])->save();

        if (blank($order->finished_at)) {
            $order->forceFill(['finished_at' => now()])->saveQuietly();
        }

        // Geser jadwal preventif berikutnya
        $order->schedule?->markDone($order->finished_at?->toDateString());

        // Kalibrasi selesai: perbarui jatuh tempo aset
        if ($order->type->value === 'kalibrasi') {
            $months = $asset->category?->calibration_interval_months ?? 12;

            $asset->forceFill([
                'calibration_due_at' => now()->addMonths($months)->toDateString(),
            ])->saveQuietly();
        }
    }

    protected function fail(WorkOrder $order): void
    {
        $order->asset->forceFill([
            'status' => AssetStatus::DiusulkanHapus,
            'condition' => \App\Enums\AssetCondition::RusakBerat,
        ])->save();

        if (blank($order->finished_at)) {
            $order->forceFill(['finished_at' => now()])->saveQuietly();
        }
    }
}