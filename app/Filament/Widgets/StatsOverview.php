<?php

namespace App\Filament\Widgets;

use App\Services\StatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
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

    /**
     * Kartu bergaya ikon di pojok kanan atas.
     * Warna kotak ikon diatur di filament/hooks/panel-style.blade.php (sp-tone-*).
     */
    protected static function kartu(string $label, mixed $nilai, string $ikon, string $nada): Stat
    {
        return Stat::make($label, $nilai)
            ->icon($ikon)
            ->color($nada === 'gray' ? 'gray' : $nada)
            ->extraAttributes(['class' => 'sp-stat sp-tone-'.$nada]);
    }

    protected function getStats(): array
    {
        $r = StatsService::publicSummary();
        $a = StatsService::actionable();

        $menunggu = (int) ($a['pengajuan_menunggu'] ?? 0);
        $wo = (int) ($a['wo_terbuka'] ?? 0);
        $terlambat = (int) ($a['pinjam_terlambat'] ?? 0);

        return [
            static::kartu('Nilai Aset', static::rupiahRingkas((float) ($r['nilai_aset'] ?? 0)), 'heroicon-o-banknotes', 'primary')
                ->description('Total perolehan'),

            static::kartu('Aset Aktif', $r['total_aset'], 'heroicon-o-cube', 'success')
                ->description($r['persen_baik'].'% dalam kondisi baik'),

            static::kartu('Menunggu Persetujuan', $menunggu, 'heroicon-o-clock', $menunggu > 0 ? 'warning' : 'gray')
                ->description('Pengajuan aset'),

            static::kartu('Perintah Kerja Terbuka', $wo, 'heroicon-o-wrench-screwdriver', $wo > 0 ? 'warning' : 'gray')
                ->description(($a['kerusakan_belum'] ?? 0).' laporan belum ditindak'),

            static::kartu('Peminjaman Terlambat', $terlambat, 'heroicon-o-exclamation-triangle', $terlambat > 0 ? 'danger' : 'gray')
                ->description('Aset belum dikembalikan'),
        ];
    }
}
