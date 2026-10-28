<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NumberSequence extends Model
{
    protected $fillable = [
        'type',
        'year',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }

    /**
     * Ambil nomor urut berikutnya. Aman dari race condition:
     * baris dikunci sampai transaksi selesai.
     */
    public static function next(string $type, ?int $year = null): int
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($type, $year) {
            $sequence = static::query()
                ->where('type', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = static::create([
                    'type' => $type,
                    'year' => $year,
                    'last_number' => 0,
                ]);

                $sequence = static::query()
                    ->whereKey($sequence->getKey())
                    ->lockForUpdate()
                    ->first();
            }

            $sequence->increment('last_number');

            return $sequence->last_number;
        });
    }

    /**
     * Format nomor dokumen lengkap.
     * Contoh: format('pengajuan', 'PGJ') => "PGJ/2026/00007"
     */
    public static function format(string $type, string $prefix, ?int $year = null, int $pad = 5): string
    {
        $year ??= (int) now()->format('Y');
        $number = static::next($type, $year);

        return sprintf('%s/%d/%s', $prefix, $year, str_pad((string) $number, $pad, '0', STR_PAD_LEFT));
    }
}
