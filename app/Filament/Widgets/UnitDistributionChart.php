<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;
use App\Support\Sapra;
use Filament\Widgets\ChartWidget;

class UnitDistributionChart extends ChartWidget
{
    protected function getMaxHeight(): ?string
    {
        return '260px';
    }

    protected ?string $heading = 'Sebaran Aset per Unit';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $rows = StatsService::assetsByUnit(8);

        return [
            'datasets' => [[
                'label' => 'Jumlah aset',
                'data' => array_column($rows, 'value'),
                'backgroundColor' => Sapra::themeColor('primary'),
                'borderRadius' => 4,
            ]],
            'labels' => array_column($rows, 'label'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'maintainAspectRatio' => false,
        ];
    }
}