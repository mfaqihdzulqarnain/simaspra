<?php

namespace App\Http\Controllers;

use App\Enums\LoanPurpose;
use App\Models\Laboratory;
use App\Models\Loan;
use App\Models\Scopes\LoanVisibilityScope;
use App\Services\LabStatsService;
use App\Support\Sapra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Cetak rekap peminjaman alat satu laboratorium.
 * Selalu satu lab per cetakan agar data tidak bercampur.
 */
class RekapLabController extends Controller
{
    /** Periode yang bisa dicetak, dalam bulan kalender */
    public const PERIODE = [1 => '1 bulan', 3 => '3 bulan', 6 => '6 bulan'];

    public function print(Request $request)
    {
        abort_unless(auth()->check(), 401);

        $user = auth()->user();
        abort_unless($user->hasAnyRole(['laboran', 'kabag_sarpras', 'super_admin']), 403);

        $data = $request->validate([
            'lab' => ['required', 'integer', 'exists:laboratories,id'],
            'periode' => ['required', 'integer', 'in:1,3,6'],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);

        $lab = Laboratory::with(['unit:id,name', 'head:id,name,nip'])->findOrFail($data['lab']);

        // Laboran hanya boleh mencetak lab yang diampunya
        if (! $user->canSeeAllUnits()) {
            abort_unless(in_array((int) $lab->getKey(), array_map('intval', $user->laboratoryIds()), true), 403);
        }

        // Rentang bulan kalender penuh, berakhir di bulan yang dipilih
        $akhirBulan = Carbon::createFromFormat('Y-m', $data['bulan'] ?? now()->format('Y-m'))->endOfMonth();
        $dari = $akhirBulan->copy()->startOfMonth()->subMonthsNoOverflow((int) $data['periode'] - 1);
        $sampai = $akhirBulan->copy()->min(now()->endOfDay());

        $pinjaman = Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->where('laboratory_id', $lab->getKey())
            ->whereBetween('start_at', [$dari, $akhirBulan])
            ->with([
                'items.asset:id,code,name,quantity',
                'consumableItems.consumable:id,name,unit_of_measure',
            ])
            ->orderBy('start_at')
            ->get();

        $logoPath = Sapra::labelLogoPath();

        $pdf = Pdf::loadView('print.rekap-lab', [
            'lab' => $lab,
            'dari' => $dari,
            'sampai' => $akhirBulan,
            'periodeLabel' => self::PERIODE[(int) $data['periode']],
            'ringkas' => LabStatsService::summary($lab->getKey(), $dari, $sampai),
            'pinjaman' => $pinjaman,
            'tersering' => LabStatsService::topAssets($lab->getKey(), $dari, $sampai, 10),
            'terlambat' => LabStatsService::lateBorrowers($lab->getKey(), $dari, $sampai, 10),
            'keperluan' => LabStatsService::purposeBreakdown($lab->getKey(), $dari, $sampai),
            'labelKeperluan' => fn (?string $v) => $v ? (LoanPurpose::tryFrom($v)?->getLabel() ?? $v) : '—',
            'institution' => Sapra::institutionName(),
            'address' => Sapra::settings()?->address,
            'phone' => Sapra::settings()?->phone,
            'email' => Sapra::settings()?->email,
            'useLetterhead' => Sapra::usesLetterheadImage(),
            'letterhead' => Sapra::letterheadDataUri(),
            'logo' => $logoPath ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null,
            'pencetak' => $user,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Rekap-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $lab->code)
            .'-'.$dari->format('Ym').'-'.$akhirBulan->format('Ym').'.pdf');
    }
}