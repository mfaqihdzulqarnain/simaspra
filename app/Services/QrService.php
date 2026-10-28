<?php

namespace App\Services;

use App\Models\Asset;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrService
{
    /**
     * QR sebagai SVG mentah.
     * SVG dipilih agar tidak butuh ekstensi Imagick dan tetap tajam saat dicetak.
     */
    public static function svg(string $payload, int $sizePx = 200): string
    {
        return QrCode::format('svg')
            ->size($sizePx)
            ->errorCorrection(config('sapra.qr.error_correction', 'Q'))
            ->margin(config('sapra.qr.margin', 1))
            ->generate($payload);
    }

    /** SVG sebagai data URI — untuk disisipkan ke <img> di dompdf */
    public static function dataUri(string $payload, int $sizePx = 200): string
    {
        $svg = static::svg($payload, $sizePx);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function forAsset(Asset $asset, int $sizePx = 200): string
    {
        return static::svg($asset->qrPayload(), $sizePx);
    }

    public static function dataUriForAsset(Asset $asset, int $sizePx = 200): string
    {
        return static::dataUri($asset->qrPayload(), $sizePx);
    }
}