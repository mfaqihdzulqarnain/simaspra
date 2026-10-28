<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Sapra
{
    protected static ?GeneralSettings $cached = null;

    public static function usesLetterheadImage(): bool
    {
        return (bool) (static::settings()?->use_letterhead_image ?? false)
            && filled(static::settings()?->letterhead);
    }

    /** Kop surat sebagai data URI — dompdf butuh path, bukan URL */
    public static function letterheadDataUri(): ?string
    {
        $path = static::settings()?->letterhead;

        if (blank($path)) {
            return null;
        }

        $full = Storage::disk('public')->path($path);

        if (! file_exists($full)) {
            return null;
        }

        $mime = mime_content_type($full) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($full));
    }

    /** Ambil settings, null kalau belum tersedia (belum migrate / DB mati) */
    public static function settings(): ?GeneralSettings
    {
        if (static::$cached) {
            return static::$cached;
        }

        try {
            return static::$cached = app(GeneralSettings::class);
        } catch (Throwable) {
            return null;
        }
    }

    /** Nilai settings dengan fallback ke config */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::settings()?->{$key} ?? null;

        if (filled($value)) {
            return $value;
        }

        return $default ?? config("sapra.{$key}");
    }

    public static function appName(): string
    {
        return static::get('app_name', config('app.name', 'SIMASPRA'));
    }

    public static function institutionName(): string
    {
        return static::get('institution_name', config('sapra.institution_name'));
    }

    public static function institutionShort(): string
    {
        return static::get('institution_short', config('sapra.institution_short'));
    }

    public static function assetCodePrefix(): string
    {
        return strtoupper(static::get('asset_code_prefix', config('sapra.asset_code_prefix')));
    }

    public static function primaryColor(): string
    {
        return static::theme()['primary'];
    }

    public static function accentColor(): string
    {
        return static::get('accent_color', config('sapra.colors.accent'));
    }

    /** URL file dari disk publik, null kalau kosong */
    public static function fileUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function logoLight(): ?string
    {
        return static::fileUrl(static::settings()?->logo_light);
    }

    public static function logoDark(): ?string
    {
        return static::fileUrl(static::settings()?->logo_dark);
    }

    public static function favicon(): ?string
    {
        return static::fileUrl(static::settings()?->favicon);
    }

    public static function reminderTemplate(): string
    {
        return static::settings()?->reminder_template
            ?: 'Yth. {nama}, peminjaman {nomor} telah melewati batas kembali {batas}. '
                .'Mohon segera dikembalikan. Terima kasih.';
    }

    /** Path absolut logo stiker — dompdf butuh path file, bukan URL */
    public static function labelLogoPath(): ?string
    {
        $path = static::settings()?->label_logo;

        if (blank($path)) {
            return null;
        }

        $full = Storage::disk('public')->path($path);

        return file_exists($full) ? $full : null;
    }

    /** Data pejabat untuk kop & tanda tangan dokumen */
    public static function signatory(string $role): array
    {
        $s = static::settings();

        return match ($role) {
            'direktur' => ['name' => $s?->director_name, 'nip' => $s?->director_nip],
            'wadir' => ['name' => $s?->vice_director_name, 'nip' => $s?->vice_director_nip],
            'sarpras' => ['name' => $s?->asset_head_name, 'nip' => $s?->asset_head_nip],
            default => ['name' => null, 'nip' => null],
        };
    }

    public static function isPublicQrEnabled(): bool
    {
        return (bool) (static::settings()?->public_qr_enabled ?? true);
    }

    public static function isPublicDamageReportEnabled(): bool
    {
        return (bool) (static::settings()?->public_damage_report_enabled ?? true);
    }

    /** Buang cache internal — dipanggil setelah settings disimpan */
    public static function flush(): void
    {
        static::$cached = null;
    }

    public static function themeKey(): string
    {
        $key = static::settings()?->theme_preset ?? 'navy';

        return array_key_exists($key, config('sapra.themes', [])) ? $key : 'navy';
    }

    /** Seluruh token warna tema aktif */
    public static function theme(): array
    {
        $preset = config('sapra.themes.'.static::themeKey());

        if (! (static::settings()?->use_custom_colors ?? false)) {
            return $preset;
        }

        return array_merge($preset, array_filter([
            'primary' => static::settings()?->primary_color,
            'accent' => static::settings()?->accent_color,
        ]));
    }

    public static function themeColor(string $token): string
    {
        return static::theme()[$token] ?? '#14315C';
    }

    public static function heroTagline(): string
    {
        return static::get('hero_tagline', 'Sistem Informasi Manajemen Sarana dan Prasarana');
    }

    public static function showsAssetValue(): bool
    {
        return (bool) (static::settings()?->show_asset_value ?? false);
    }

    /** Rupiah ringkas: Rp 9,5 jt · Rp 150 jt · Rp 1,2 M */
    public static function rupiahRingkas(float|int|string|null $nilai): string
    {
        $nilai = (float) $nilai;

        $ringkas = fn (float $angka, string $satuan) => 'Rp '
            .rtrim(rtrim(number_format($angka, 1, ',', '.'), '0'), ',')
            .' '.$satuan;

        return match (true) {
            $nilai >= 1_000_000_000 => $ringkas($nilai / 1_000_000_000, 'M'),
            $nilai >= 1_000_000 => $ringkas($nilai / 1_000_000, 'jt'),
            $nilai >= 1_000 => 'Rp '.number_format($nilai / 1_000, 0, ',', '.').' rb',
            default => 'Rp '.number_format($nilai, 0, ',', '.'),
        };
    }

    /** Rupiah lengkap untuk tooltip dan detail */
    public static function rupiah(float|int|string|null $nilai): string
    {
        return 'Rp '.number_format((float) $nilai, 0, ',', '.');
    }
}
