<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;
use Filament\Widgets\ChartWidget;

class ConditionChart extends ChartWidget
{
    protected ?string $heading = 'Komposisi Kondisi Aset';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getMaxHeight(): ?string
    {
        return '260px';
    }

    protected function getData(): array
    {
        $rows = StatsService::conditionBreakdown();

        return [
            'datasets' => [[
                'data' => array_column($rows, 'value'),
                'backgroundColor' => array_column($rows, 'color'),
                'borderWidth' => 0,
            ]],
            'labels' => array_column($rows, 'label'),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'maintainAspectRatio' => false,
        ];
    }
}