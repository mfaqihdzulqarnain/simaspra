<?php

namespace App\Exports;

use App\Exports\Sheets\AssetTemplateDataSheet;
use App\Exports\Sheets\AssetTemplateGuideSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Templat impor aset: lembar "Data Aset" untuk diisi, dan lembar "Petunjuk".
 * Importer hanya membaca baris yang punya nama_barang, jadi lembar Petunjuk aman ikut terunggah.
 *
 * Export: penanda wajib di maatwebsite/excel versi ini agar Excel::download() menerima
 * kelas multi-lembar (WithMultipleSheets tidak membawanya sendiri).
 */
class AssetTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new AssetTemplateDataSheet,
            new AssetTemplateGuideSheet,
        ];
    }
}