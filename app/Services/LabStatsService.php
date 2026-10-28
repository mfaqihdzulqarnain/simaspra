<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Scopes\LoanVisibilityScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LabStatsService
{
    /** Ringkasan angka untuk satu lab pada rentang waktu */
    public static function summary(?int $labId, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $q = fn () => Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->when($labId, fn ($b) => $b->where('laboratory_id', $labId))
            ->whereBetween('start_at', [$dari, $sampai]);

        $total = $q()->count();
        $selesai = (clone $q())->where('status', LoanStatus::Dikembalikan)->count();

        $terlambat = (clone $q())
            ->where('status', LoanStatus::Dikembalikan)
            ->whereColumn('returned_at', '>', 'end_at')
            ->count();

        $berjalan = Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->when($labId, fn ($b) => $b->where('laboratory_id', $labId))
            ->ongoing()
            ->count();

        $alat = Asset::query()
            ->allUnits()
            ->active()
            ->when($labId, fn ($b) => $b->inLaboratory($labId))
            ->count();

        $alatPinjam = Asset::query()
            ->allUnits()
            ->active()
            ->where('is_loanable', true)
            ->when($labId, fn ($b) => $b->inLaboratory($labId))
            ->count();

        return [
            'total_pinjaman' => $total,
            'selesai' => $selesai,
            'berjalan' => $berjalan,
            'terlambat' => $terlambat,
            'persen_terlambat' => $selesai > 0 ? round($terlambat / $selesai * 100, 1) : 0,
            'total_alat' => $alat,
            'alat_dipinjamkan' => $alatPinjam,
        ];
    }

    /** Alat paling sering dipinjam */
    public static function topAssets(?int $labId, CarbonInterface $dari, CarbonInterface $sampai, int $limit = 10): Collection
    {
        return LoanItem::query()
            ->selectRaw('asset_id, COUNT(*) as jumlah')
            ->whereHas('loan', fn ($q) => $q
                ->withoutGlobalScope(LoanVisibilityScope::class)
                ->when($labId, fn ($b) => $b->where('laboratory_id', $labId))
                ->whereBetween('start_at', [$dari, $sampai])
                ->whereNotIn('status', [
                    LoanStatus::Ditolak->value,
                    LoanStatus::Batal->value,
                ]))
            ->with('asset:id,code,name')
            ->groupBy('asset_id')
            ->orderByDesc('jumlah')
            ->limit($limit)
            ->get()
            ->filter(fn ($r) => $r->asset !== null)
            ->values();
    }

    /** Peminjam yang paling sering terlambat */
    public static function lateBorrowers(?int $labId, CarbonInterface $dari, CarbonInterface $sampai, int $limit = 10): Collection
    {
        return Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->selectRaw('borrower_name, borrower_identity, borrower_phone, COUNT(*) as jumlah')
            ->when($labId, fn ($b) => $b->where('laboratory_id', $labId))
            ->whereBetween('start_at', [$dari, $sampai])
            ->where(function ($q) {
                $q->where(fn ($b) => $b
                    ->where('status', LoanStatus::Dikembalikan)
                    ->whereColumn('returned_at', '>', 'end_at'))
                    ->orWhere('status', LoanStatus::Terlambat);
            })
            ->groupBy('borrower_name', 'borrower_identity', 'borrower_phone')
            ->orderByDesc('jumlah')
            ->limit($limit)
            ->get();
    }

    /** Alat yang belum pernah dipinjam sama sekali pada rentang ini */
    public static function idleAssets(?int $labId, CarbonInterface $dari, CarbonInterface $sampai, int $limit = 15): Collection
    {
        $terpakai = LoanItem::query()
            ->whereHas('loan', fn ($q) => $q
                ->withoutGlobalScope(LoanVisibilityScope::class)
                ->whereBetween('start_at', [$dari, $sampai]))
            ->pluck('asset_id')
            ->unique();

        return Asset::query()
            ->allUnits()
            ->active()
            ->where('is_loanable', true)
            ->when($labId, fn ($b) => $b->inLaboratory($labId))
            ->whereNotIn('id', $terpakai)
            ->with('room:id,name')
            ->orderBy('code')
            ->limit($limit)
            ->get();
    }

    /** Jumlah peminjaman per bulan sepanjang rentang yang dipilih */
    public static function monthlyTrend(?int $labId, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $mulai = Carbon::parse($dari)->startOfMonth();
        $akhir = Carbon::parse($sampai)->startOfMonth();

        // Batasi agar grafik tetap terbaca bila rentangnya panjang
        $maksBulan = 12;

        if ($mulai->diffInMonths($akhir) > $maksBulan) {
            $mulai = $akhir->copy()->subMonths($maksBulan);
        }

        $bulan = collect();
        $cursor = $mulai->copy();

        while ($cursor <= $akhir) {
            $bulan->push($cursor->copy());
            $cursor->addMonth();
        }

        $data = Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->when($labId, fn ($b) => $b->where('laboratory_id', $labId))
            ->whereBetween('start_at', [$dari, $sampai])
            ->selectRaw("DATE_FORMAT(start_at, '%Y-%m') as periode, COUNT(*) as jumlah")
            ->groupBy('periode')
            ->pluck('jumlah', 'periode')
            ->all();

        return $bulan->map(fn (Carbon $m) => [
            'label' => $m->locale('id')->translatedFormat('M y'),
            'jumlah' => (int) ($data[$m->format('Y-m')] ?? 0),
        ])->all();
    }

    /** Sebaran keperluan peminjaman */
    public static function purposeBreakdown(?int $labId, CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        return Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->selectRaw('purpose_type, COUNT(*) as jumlah')
            ->when($labId, fn ($b) => $b->where('laboratory_id', $labId))
            ->whereBetween('start_at', [$dari, $sampai])
            ->whereNotNull('purpose_type')
            ->groupBy('purpose_type')
            ->orderByDesc('jumlah')
            ->get();
    }
}
