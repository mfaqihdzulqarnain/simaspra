<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // protected int|array|null $columns = 6;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = [
        'default' => 1,
        'md' => 3,
        'xl' => 5,
    ];

    protected static function rupiahRingkas(float $nilai): string
    {
        return match (true) {
            $nilai >= 1_000_000_000 => 'Rp '.number_format($nilai / 1_000_000_000, 1, ',', '.').' M',
            $nilai >= 1_000_000 => 'Rp '.number_format($nilai / 1_000_000, 1, ',', '.').' jt',
            $nilai >= 1_000 => 'Rp '.number_format($nilai / 1_000, 0, ',', '.').' rb',
            default => 'Rp '.number_format($nilai, 0, ',', '.'),
        };
    }

    protected function getStats(): array
    {
        $r = StatsService::publicSummary();
        $a = StatsService::actionable();

        return [
            Stat::make('Nilai Aset', static::rupiahRingkas((float) ($r['nilai_aset'] ?? 0)))
                ->description('Total perolehan')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Aset Aktif', $r['total_aset'])
                ->description($r['persen_baik'].'% dalam kondisi baik')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Menunggu Persetujuan', $a['pengajuan_menunggu'] ?? 0)
                ->description('Pengajuan aset')
                ->descriptionIcon('heroicon-m-clock')
                ->color(($a['pengajuan_menunggu'] ?? 0) > 0 ? 'warning' : 'gray'),

            Stat::make('Perintah Kerja Terbuka', $a['wo_terbuka'] ?? 0)
                ->description(($a['kerusakan_belum'] ?? 0).' laporan belum ditindak')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color(($a['wo_terbuka'] ?? 0) > 0 ? 'warning' : 'gray'),

            Stat::make('Peminjaman Terlambat', $a['pinjam_terlambat'] ?? 0)
                ->description('Aset belum dikembalikan')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color(($a['pinjam_terlambat'] ?? 0) > 0 ? 'danger' : 'gray'),

            // Stat::make('Jadwal Terlambat', $a['jadwal_terlambat'] ?? 0)
            //     ->description(($a['garansi_segera'] ?? 0).' garansi akan habis')
            //     ->descriptionIcon('heroicon-m-calendar-days')
            //     ->color(($a['jadwal_terlambat'] ?? 0) > 0 ? 'danger' : 'gray'),
        ];
    }
}
