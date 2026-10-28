<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public bool $use_custom_colors;

    public string $theme_preset;

    public ?string $hero_tagline;

    public bool $show_asset_value;

    public ?string $letterhead;

    public ?string $reminder_template;

    public bool $use_letterhead_image;

    // Identitas
    public string $app_name;

    public string $institution_name;

    public string $institution_short;

    // Logo & warna
    public ?string $logo_light;

    public ?string $logo_dark;

    public ?string $favicon;

    public ?string $label_logo;

    public string $primary_color;

    public string $accent_color;

    // Kontak
    public ?string $address;

    public ?string $phone;

    public ?string $email;

    public ?string $website;

    // Pejabat penanda tangan dokumen
    public ?string $director_name;

    public ?string $director_nip;

    public ?string $vice_director_name;

    public ?string $vice_director_nip;

    public ?string $asset_head_name;

    public ?string $asset_head_nip;

    // Kode & QR
    public string $asset_code_prefix;

    public bool $public_qr_enabled;

    public bool $public_damage_report_enabled;

    public static function group(): string
    {
        return 'general';
    }

    /** Kolom yang tidak boleh ikut ter-cache mentah */
    public static function encrypted(): array
    {
        return [];
    }
}
