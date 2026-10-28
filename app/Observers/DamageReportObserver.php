<?php

namespace App\Observers;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\DamageReport;
use App\Services\NotifyService;

class DamageReportObserver
{
    public function created(DamageReport $report): void
    {
        $judul = 'Laporan kerusakan baru';
        $isi = $report->item_label.' — '.$report->severity->getLabel().' · dilaporkan '.$report->reporter_label;
        $url = DamageReportResource::getUrl('index');
        $warna = $report->severity->value === 'berat' ? 'danger' : 'warning';
        $ikon = 'heroicon-o-exclamation-triangle';

        NotifyService::toRoles(['kabag_sarpras'], $judul, $isi, $url, $warna, $ikon);

        // Barang laboratorium: laboran lab bersangkutan ikut diberi tahu
        if ($labId = $report->laboratoryId()) {
            NotifyService::toLabStaff($labId, $judul, $isi, $url, $warna, $ikon);
        }
    }
}