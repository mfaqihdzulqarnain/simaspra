<?php

namespace App\Services;

use App\Models\Asset;
use App\Support\Sapra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class LabelService
{
    /** Label berurutan untuk printer thermal 60×40 mm */
    public static function thermal(Collection $assets, bool $markPrinted = true): \Barryvdh\DomPDF\PDF
    {
        $assets->loadMissing('unit');

        $pdf = Pdf::loadView('print.label', [
            'assets' => $assets,
            'qrs' => static::qrMap($assets, 400),
            'institution' => Sapra::institutionShort(),
            'labelLogo' => static::logoDataUri(),
        ])->setPaper([0, 0, 170.08, 113.39]); // 60×40 mm

        if ($markPrinted) {
            static::markPrinted($assets);
        }

        return $pdf;
    }

    /** Lembar A4 berisi 18 label (3 kolom × 6 baris) */
    public static function sheet(Collection $assets, int $startPosition = 1, bool $markPrinted = true): \Barryvdh\DomPDF\PDF
    {
        $assets->loadMissing('unit');

        $columns = config('sapra.label.per_sheet.columns', 2);
        $rows = config('sapra.label.per_sheet.rows', 5);
        $perSheet = $columns * $rows;

        $items = collect(array_fill(0, max($startPosition - 1, 0), null))
            ->concat($assets->all());

        // Genapkan hingga lembar terakhir penuh
        $total = (int) ceil($items->count() / $perSheet) * $perSheet;
        $items = $items->pad(max($total, $perSheet), null);

        // Pecah per lembar, lalu per baris — jumlah baris terkunci
        $sheets = $items->chunk($perSheet)->map(function (BaseCollection $sheet) use ($columns) {
            return $sheet->chunk($columns)
                ->map(fn (BaseCollection $row) => array_pad($row->values()->all(), $columns, null))
                ->values()
                ->all();
        })->values()->all();

        $pdf = Pdf::loadView('print.label-sheet', [
            'sheets' => $sheets,
            'qrs' => static::qrMap($assets, 200),
            'institution' => Sapra::institutionShort(),
            'labelLogo' => static::logoDataUri(),
        ])->setPaper('a4', 'portrait');

        if ($markPrinted) {
            static::markPrinted($assets);
        }

        return $pdf;
    }

    /** Peta id aset ke data URI QR */
    protected static function qrMap(Collection $assets, int $sizePx): array
    {
        return $assets->mapWithKeys(fn (Asset $asset) => [
            $asset->id => QrService::dataUriForAsset($asset, $sizePx),
        ])->all();
    }

    protected static function logoDataUri(): ?string
    {
        $path = Sapra::labelLogoPath();

        if (! $path) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    protected static function markPrinted(Collection $assets): void
    {
        $assets->each(fn (Asset $asset) => $asset->markLabelPrinted());
    }

    public static function filename(Collection $assets): string
    {
        return $assets->count() === 1
            ? 'label-'.$assets->first()->code.'.pdf'
            : 'label-aset-'.now()->format('Ymd-His').'.pdf';
    }
}