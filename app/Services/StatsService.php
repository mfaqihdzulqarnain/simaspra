<?php

namespace App\Services;

use App\Enums\AssetCondition;
// use App\Enums\AssetStatus;
// use App\Enums\LoanStatus;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\DamageReport;
use App\Models\Laboratory;
use App\Models\Loan;
use App\Models\MaintenanceSchedule;
use App\Models\ProcurementRequest;
use App\Models\Room;
use App\Models\Scopes\LoanVisibilityScope;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

class StatsService
{
    /** Angka untuk halaman publik — di-cache 15 menit */
    public static function publicSummary(): array
    {
        return Cache::remember('stats.public', now()->addMinutes(15), function () {
            $assets = Asset::query()->allUnits()->active();

            $total = (clone $assets)->count();
            $baik = (clone $assets)->where('condition', AssetCondition::Baik)->count();

            return [
                'total_aset' => $total,
                'total_unit' => Unit::where('is_active', true)->count(),
                'total_ruang' => Room::where('is_active', true)->count(),
                'persen_baik' => $total > 0 ? round($baik / $total * 100) : 0,
                'nilai_aset' => (float) (clone $assets)->sum('acquisition_value'),
            ];
        });
    }

    /** Sebaran aset per unit untuk grafik batang */
    public static function assetsByUnit(int $limit = 10): array
    {
        return Cache::remember('stats.by_unit', now()->addMinutes(15), function () use ($limit) {
            return Unit::query()
                ->where('is_active', true)
                ->withCount(['assets' => fn ($q) => $q->active()])
                ->orderByDesc('assets_count')
                ->limit($limit)
                ->get()
                ->map(fn (Unit $u) => [
                    'label' => $u->name,
                    'value' => $u->assets_count,
                ])
                ->all();
        });
    }

    /** Komposisi kondisi aset */
    public static function conditionBreakdown(): array
    {
        $rows = Asset::query()
            ->allUnits()
            ->active()
            ->selectRaw('`condition`, COUNT(*) as total')
            ->groupBy('condition')
            ->pluck('total', 'condition')
            ->all();

        return collect(AssetCondition::cases())
            ->map(fn (AssetCondition $c) => [
                'label' => $c->getLabel(),
                'value' => (int) ($rows[$c->value] ?? 0),
                'color' => match ($c->getColor()) {
                    'success' => '#12B76A',
                    'warning' => '#F79009',
                    default => '#D92D20',
                },
            ])
            ->all();
    }

    /** Tren pengajuan per bulan: diajukan dan disetujui */
    public static function requestTrend(int $months = 12): array
    {
        $months = max(1, $months);
        $awal = now()->startOfMonth();

        $bulan = collect(range($months - 1, 0))
            ->map(fn (int $i) => $awal->copy()->subMonthsNoOverflow($i));

        $data = ProcurementRequest::query()
            ->allUnits()
            ->where('created_at', '>=', $bulan->first())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as periode, status, COUNT(*) as total")
            ->groupBy('periode', 'status')
            ->get();

        return [
            'labels' => $bulan->map(fn ($m) => $m->locale('id')->translatedFormat('M y'))->all(),
            'diajukan' => $bulan->map(fn ($m) => (int) $data
                ->where('periode', $m->format('Y-m'))
                ->sum('total'))->all(),
            'disetujui' => $bulan->map(fn ($m) => (int) $data
                ->where('periode', $m->format('Y-m'))
                ->whereIn('status', [
                    RequestStatus::Disetujui,
                    RequestStatus::Disalurkan,
                    RequestStatus::Selesai,
                ])
                ->sum('total'))->all(),
        ];
    }

    /**
     * Aset baru yang masuk tanpa pengajuan: hibah, impor Excel, atau dicatat langsung.
     * Dihitung per bulan pencatatan, dalam unit (aset kolektif dihitung jumlah unitnya).
     *
     * @return array{labels: array<string>, unit: array<int>, aset: array<int>}
     */
    public static function assetIntakeTrend(int $months = 12): array
    {
        $months = max(1, $months);
        $awal = now()->startOfMonth();

        $bulan = collect(range($months - 1, 0))
            ->map(fn (int $i) => $awal->copy()->subMonthsNoOverflow($i));

        $data = Asset::query()
            ->allUnits()
            ->whereNull('procurement_request_item_id')
            ->where('created_at', '>=', $bulan->first())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as periode, COUNT(*) as aset, SUM(quantity) as unit")
            ->groupBy('periode')
            ->get()
            ->keyBy('periode');

        return [
            'labels' => $bulan->map(fn ($m) => $m->locale('id')->translatedFormat('M y'))->all(),
            'unit' => $bulan->map(fn ($m) => (int) ($data[$m->format('Y-m')]->unit ?? 0))->all(),
            'aset' => $bulan->map(fn ($m) => (int) ($data[$m->format('Y-m')]->aset ?? 0))->all(),
        ];
    }

    /** Biaya pemeliharaan per bulan */
    public static function maintenanceCostTrend(): array
    {
        $bulan = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i));

        $data = WorkOrder::query()
            ->where('status', WorkOrderStatus::Selesai)
            ->where('finished_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("DATE_FORMAT(finished_at, '%Y-%m') as periode, SUM(cost) as total")
            ->groupBy('periode')
            ->pluck('total', 'periode')
            ->all();

        return [
            'labels' => $bulan->map(fn ($m) => $m->locale('id')->translatedFormat('M y'))->all(),
            'values' => $bulan->map(fn ($m) => (float) ($data[$m->format('Y-m')] ?? 0))->all(),
        ];
    }

    /** Angka yang butuh perhatian, untuk dashboard admin */
    public static function actionable(): array
    {
        return [
            'ajuan_menunggu' => ProcurementRequest::query()->allUnits()->whereIn('status', [
                RequestStatus::VerifikasiSarpras->value,
                RequestStatus::MenungguWadir->value,
            ])->count(),

            'kerusakan_belum' => DamageReport::where('is_processed', false)->count(),

            'wo_terbuka' => WorkOrder::query()->open()->count(),

            'pinjam_terlambat' => Loan::query()->overdue()->count(),

            'jadwal_terlambat' => MaintenanceSchedule::query()->overdue()->count(),

            'aset_belum_label' => Asset::query()->allUnits()->active()->unlabeled()->count(),

            'aset_rusak_berat' => Asset::query()->allUnits()->active()
                ->where('condition', AssetCondition::RusakBerat)->count(),

            'garansi_habis' => Asset::query()->allUnits()->active()->warrantyExpiring(30)->count(),
        ];
    }

    public static function flush(): void
    {
        Cache::forget('stats.public');
        Cache::forget('stats.by_unit');
        Cache::forget('stats.lab');
        Cache::forget('stats.activity.6');
    }

    /** Jumlah alat di ruang laboratorium, untuk halaman publik */
    public static function labSummary(): array
    {
        return Cache::remember('stats.lab', now()->addMinutes(15), fn () => [
            'alat' => Asset::query()
                ->allUnits()
                ->active()
                ->whereIn('room_id', Room::withoutGlobalScopes()
                    ->whereNotNull('laboratory_id')
                    ->select('id'))
                ->count(),
            'lab' => Laboratory::withoutGlobalScopes()->where('is_active', true)->count(),
        ]);
    }

    /** Aset baru dan peminjaman per bulan, untuk halaman publik */
    public static function publicActivity(int $months = 6): array
    {
        return Cache::remember("stats.activity.{$months}", now()->addMinutes(15), function () use ($months) {
            $awal = now()->startOfMonth();
            $bulan = collect(range($months - 1, 0))
                ->map(fn (int $i) => $awal->copy()->subMonthsNoOverflow($i));
            $mulai = $bulan->first();

            $aset = Asset::query()
                ->allUnits()
                ->where('acquisition_date', '>=', $mulai)
                ->selectRaw("DATE_FORMAT(acquisition_date, '%Y-%m') as periode, COUNT(*) as total")
                ->groupBy('periode')
                ->pluck('total', 'periode')
                ->all();

            // Pengunjung publik tidak login, jadi scope visibilitas wajib dilepas
            $pinjam = Loan::query()
                ->withoutGlobalScope(LoanVisibilityScope::class)
                ->whereIn('status', [
                    LoanStatus::Dipinjam->value,
                    LoanStatus::Dikembalikan->value,
                    LoanStatus::Terlambat->value,
                ])
                ->where('start_at', '>=', $mulai)
                ->selectRaw("DATE_FORMAT(start_at, '%Y-%m') as periode, COUNT(*) as total")
                ->groupBy('periode')
                ->pluck('total', 'periode')
                ->all();

            return [
                'labels' => $bulan->map(fn ($m) => $m->locale('id')->translatedFormat('M'))->all(),
                'aset' => $bulan->map(fn ($m) => (int) ($aset[$m->format('Y-m')] ?? 0))->all(),
                'pinjam' => $bulan->map(fn ($m) => (int) ($pinjam[$m->format('Y-m')] ?? 0))->all(),
            ];
        });
    }
}
