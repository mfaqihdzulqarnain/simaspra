<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;

/**
 * Tren aset baru yang masuk tanpa pengajuan: hibah, impor, atau dicatat langsung.
 * Berdampingan dengan Tren Pengajuan Aset, dengan filter dan gaya yang sama.
 */
class AssetIntakeChart extends RequestTrendChart
{
    protected ?string $heading = 'Tren Aset Baru';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function getDescription(): ?string
    {
        return parent::getDescription().' · tanpa pengajuan';
    }

    protected function getData(): array
    {
        $tren = StatsService::assetIntakeTrend((int) ($this->filter ?? 12));

        return [
            'datasets' => [
                $this->seri('Unit tercatat', $tren['unit'], '#7C3AED'),
                $this->seri('Kode aset', $tren['aset'], '#F79009'),
            ],
            'labels' => $tren['labels'],
        ];
    }
}