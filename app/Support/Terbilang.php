<?php

namespace App\Support;

/** Angka ke kata dalam bahasa Indonesia, untuk kuitansi */
class Terbilang
{
    protected const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    public static function rupiah(float|int|string|null $nilai): string
    {
        $bulat = (int) round((float) $nilai);

        if ($bulat === 0) {
            return 'nol rupiah';
        }

        return trim(preg_replace('/\s+/', ' ', static::kata($bulat))).' rupiah';
    }

    public static function kata(int $n): string
    {
        $n = abs($n);

        return match (true) {
            $n === 0 => '',
            $n < 12 => ' '.static::SATUAN[$n],
            $n < 20 => static::kata($n - 10).' belas',
            $n < 100 => static::kata(intdiv($n, 10)).' puluh'.static::kata($n % 10),
            $n < 200 => ' seratus'.static::kata($n - 100),
            $n < 1000 => static::kata(intdiv($n, 100)).' ratus'.static::kata($n % 100),
            $n < 2000 => ' seribu'.static::kata($n - 1000),
            $n < 1_000_000 => static::kata(intdiv($n, 1000)).' ribu'.static::kata($n % 1000),
            $n < 1_000_000_000 => static::kata(intdiv($n, 1_000_000)).' juta'.static::kata($n % 1_000_000),
            $n < 1_000_000_000_000 => static::kata(intdiv($n, 1_000_000_000)).' miliar'.static::kata($n % 1_000_000_000),
            default => static::kata(intdiv($n, 1_000_000_000_000)).' triliun'.static::kata($n % 1_000_000_000_000),
        };
    }
}
