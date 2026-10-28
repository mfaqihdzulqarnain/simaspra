<?php

namespace App\Services;

use App\Enums\RateUnit;
use App\Models\RentalDecree;
use App\Models\RentalRate;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hitung biaya sewa ruang untuk pihak luar.
 *
 * Aturan (sesuai SK biaya):
 * - Hanya jam acara yang dihitung; hari persiapan dan beres-beres tidak.
 * - Pelengkap sudah termasuk sewa ruang.
 * - Setiap hari acara dihitung terpisah. Untuk tiap hari dipilih tarif termurah
 *   yang mencukupi: tarif sesi bila lama acara hari itu tidak melebihi batas
 *   jam sesi, atau tarif harian.
 * - Bila hanya ada tarif sesi dan acara lebih lama dari sesi terpanjang,
 *   sesi terpanjang dikalikan sampai mencukupi.
 */
class RentalFeeService
{
    /**
     * @return array{
     *     total: float,
     *     decree: ?RentalDecree,
     *     rincian: array<int, array{tanggal: string, jam: string, durasi: float, tarif: string, harga: float}>,
     *     pesan: ?string
     * }
     */
    public static function calculate(
        ?Room $room,
        CarbonInterface|string|null $start,
        CarbonInterface|string|null $end,
        CarbonInterface|string|null $tanggalTarif = null,
    ): array {
        $kosong = ['total' => 0.0, 'decree' => null, 'rincian' => [], 'pesan' => null];

        if (! $room || blank($start) || blank($end)) {
            return $kosong;
        }

        $mulai = Carbon::parse($start);
        $selesai = Carbon::parse($end);

        if ($selesai->lte($mulai)) {
            return $kosong;
        }

        $decree = RentalDecree::current($tanggalTarif);

        if (! $decree) {
            return ['pesan' => 'Belum ada SK tarif sewa yang berlaku.'] + $kosong;
        }

        $tarif = $decree->rates()->where('room_id', $room->getKey())->get();

        if ($tarif->isEmpty()) {
            return [
                'decree' => $decree,
                'pesan' => 'Tarif untuk '.$room->name.' belum tercantum di SK '.$decree->number.'.',
            ] + $kosong;
        }

        $rincian = [];
        $hari = $mulai->copy()->startOfDay();

        while ($hari->lt($selesai)) {
            $awalHari = $hari->copy()->max($mulai);
            $akhirHari = $hari->copy()->addDay()->min($selesai);
            $jam = round($awalHari->diffInMinutes($akhirHari) / 60, 2);

            if ($jam > 0) {
                [$label, $harga] = static::pilihTarif($tarif, $jam);

                $rincian[] = [
                    'tanggal' => $hari->locale('id')->translatedFormat('D, j M Y'),
                    'jam' => $awalHari->format('H.i').'–'.($akhirHari->isStartOfDay() ? '24.00' : $akhirHari->format('H.i')),
                    'durasi' => $jam,
                    'tarif' => $label,
                    'harga' => $harga,
                ];
            }

            $hari->addDay();
        }

        return [
            'total' => (float) collect($rincian)->sum('harga'),
            'decree' => $decree,
            'rincian' => $rincian,
            'pesan' => null,
        ];
    }

    /**
     * Tarif termurah yang mencukupi lama acara dalam satu hari.
     *
     * @return array{0: string, 1: float}
     */
    protected static function pilihTarif(Collection $tarif, float $jam): array
    {
        $calon = collect();

        foreach ($tarif as $t) {
            /** @var RentalRate $t */
            if ($t->unit === RateUnit::Hari) {
                $calon->push([$t->display_label, (float) $t->price]);

                continue;
            }

            if ($t->session_hours && $jam <= (float) $t->session_hours) {
                $calon->push([$t->display_label, (float) $t->price]);
            }
        }

        if ($calon->isEmpty()) {
            // Hanya ada tarif sesi dan acara lebih panjang: ulangi sesi terpanjang
            $sesi = $tarif
                ->filter(fn (RentalRate $t) => $t->unit === RateUnit::Sesi && $t->session_hours > 0)
                ->sortByDesc('session_hours')
                ->first();

            if (! $sesi) {
                return ['Tarif tidak lengkap', 0.0];
            }

            $kali = (int) ceil($jam / (float) $sesi->session_hours);

            return [$sesi->display_label.' × '.$kali, (float) $sesi->price * $kali];
        }

        return $calon->sortBy(fn ($c) => $c[1])->first();
    }

    /** Ringkasan satu baris untuk keterangan form */
    public static function summary(array $hasil): string
    {
        if ($hasil['pesan']) {
            return $hasil['pesan'];
        }

        if (empty($hasil['rincian'])) {
            return 'Isi ruang dan jadwal untuk melihat perkiraan biaya.';
        }

        return 'Perkiraan biaya: '.static::rupiah($hasil['total'])
            .' ('.count($hasil['rincian']).' hari acara, SK '.$hasil['decree']?->number.'). '
            .'Hari persiapan dan beres-beres tidak dihitung; pelengkap sudah termasuk.';
    }

    public static function rupiah(float|int|string|null $nilai): string
    {
        return 'Rp '.number_format((float) $nilai, 0, ',', '.');
    }
}
