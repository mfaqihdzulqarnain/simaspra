<?php

namespace App\Support;

class Phone
{
    /** Ubah ke format internasional tanpa tanda plus: 6281234567890 */
    public static function normalize(?string $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        $digit = preg_replace('/\D+/', '', $raw);

        if ($digit === '') {
            return null;
        }

        return match (true) {
            str_starts_with($digit, '62') => $digit,
            str_starts_with($digit, '0') => '62' . substr($digit, 1),
            str_starts_with($digit, '8') => '62' . $digit,
            default => $digit,
        };
    }

    /** Tampilan ramah: 0812-3456-7890 */
    public static function display(?string $number): string
    {
        $n = static::normalize($number);

        if (! $n) {
            return '—';
        }

        $lokal = str_starts_with($n, '62') ? '0' . substr($n, 2) : $n;

        return trim(chunk_split($lokal, 4, '-'), '-');
    }

    /** Tautan wa.me dengan pesan terisi */
    public static function waLink(?string $number, string $message = ''): ?string
    {
        $n = static::normalize($number);

        if (! $n) {
            return null;
        }

        return 'https://wa.me/' . $n . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }
}