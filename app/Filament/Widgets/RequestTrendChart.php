<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;
use App\Support\Sapra;
use Filament\Widgets\ChartWidget;

class RequestTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tren Pengajuan Aset';

    protected static ?int $sort = 2;

    // Setengah lebar, berdampingan dengan Tren Aset Baru
    protected int|string|array $columnSpan = 1;

    /** Tidak perlu polling tiap 5 detik untuk data bulanan */
    protected ?string $pollingInterval = null;

    public ?string $filter = '12';

    protected function getMaxHeight(): ?string
    {
        return '260px';
    }

    protected function getFilters(): ?array
    {
        return [
            '3' => '3 bulan',
            '6' => '6 bulan',
            '12' => '1 tahun',
        ];
    }

    public function getDescription(): ?string
    {
        return match ($this->filter) {
            '3' => '3 bulan terakhir',
            '6' => '6 bulan terakhir',
            default => '12 bulan terakhir',
        };
    }

    protected function getData(): array
    {
        $bulan = (int) ($this->filter ?? 12);
        $tren = StatsService::requestTrend($bulan);

        $aksen = Sapra::themeColor('accent');
        $hijau = '#12B76A';

        return [
            'datasets' => [
                $this->seri('Diajukan', $tren['diajukan'], $aksen),
                $this->seri('Disetujui', $tren['disetujui'], $hijau),
            ],
            'labels' => $tren['labels'],
        ];
    }

    /** Dipakai juga oleh AssetIntakeChart, karena itu protected */
    protected function seri(string $label, array $data, string $warna): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $warna,
            'backgroundColor' => static::transparan($warna, 0.10),
            'fill' => 'origin',
            'borderWidth' => 2.5,
            'cubicInterpolationMode' => 'monotone',
            'pointRadius' => 3,
            'pointHoverRadius' => 6,
            'pointBackgroundColor' => $warna,
            'pointBorderColor' => $warna,
            'pointHoverBorderWidth' => 3,
            'pointHoverBorderColor' => static::transparan($warna, 0.25),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'animation' => ['duration' => 600, 'easing' => 'easeOutQuart'],
            'layout' => ['padding' => ['top' => 8]],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'pointStyle' => 'circle',
                        'boxWidth' => 8,
                        'boxHeight' => 8,
                        'padding' => 18,
                    ],
                ],
                'tooltip' => [
                    'padding' => 10,
                    'boxPadding' => 4,
                    'usePointStyle' => true,
                    'cornerRadius' => 8,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'border' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grace' => '15%',
                    'ticks' => ['precision' => 0, 'maxTicksLimit' => 5, 'padding' => 8],
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.15)'],
                    'border' => ['display' => false],
                ],
            ],
        ];
    }

    /** Ubah hex menjadi rgba untuk isian area */
    protected static function transparan(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return "rgba(59, 130, 246, {$alpha})";
        }

        [$r, $g, $b] = sscanf($hex, '%02x%02x%02x');

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}