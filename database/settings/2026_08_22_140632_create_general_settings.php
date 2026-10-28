<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Identitas
        $this->migrator->add('general.app_name', 'SIMASPRA');
        $this->migrator->add('general.institution_name', 'Politeknik Aisyiyah');
        $this->migrator->add('general.institution_short', 'Poltek Aisyiyah');

        // Logo & warna
        $this->migrator->add('general.logo_light', null);
        $this->migrator->add('general.logo_dark', null);
        $this->migrator->add('general.favicon', null);
        $this->migrator->add('general.label_logo', null);
        $this->migrator->add('general.primary_color', '#14315C');
        $this->migrator->add('general.accent_color', '#2E90FA');

        // Kontak
        $this->migrator->add('general.address', null);
        $this->migrator->add('general.phone', null);
        $this->migrator->add('general.email', null);
        $this->migrator->add('general.website', null);

        // Pejabat
        $this->migrator->add('general.director_name', null);
        $this->migrator->add('general.director_nip', null);
        $this->migrator->add('general.vice_director_name', null);
        $this->migrator->add('general.vice_director_nip', null);
        $this->migrator->add('general.asset_head_name', null);
        $this->migrator->add('general.asset_head_nip', null);

        // Kode & QR
        $this->migrator->add('general.asset_code_prefix', 'PA');
        $this->migrator->add('general.public_qr_enabled', true);
        $this->migrator->add('general.public_damage_report_enabled', true);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.app_name');
        $this->migrator->deleteIfExists('general.institution_name');
        $this->migrator->deleteIfExists('general.institution_short');
        $this->migrator->deleteIfExists('general.logo_light');
        $this->migrator->deleteIfExists('general.logo_dark');
        $this->migrator->deleteIfExists('general.favicon');
        $this->migrator->deleteIfExists('general.label_logo');
        $this->migrator->deleteIfExists('general.primary_color');
        $this->migrator->deleteIfExists('general.accent_color');
        $this->migrator->deleteIfExists('general.address');
        $this->migrator->deleteIfExists('general.phone');
        $this->migrator->deleteIfExists('general.email');
        $this->migrator->deleteIfExists('general.website');
        $this->migrator->deleteIfExists('general.director_name');
        $this->migrator->deleteIfExists('general.director_nip');
        $this->migrator->deleteIfExists('general.vice_director_name');
        $this->migrator->deleteIfExists('general.vice_director_nip');
        $this->migrator->deleteIfExists('general.asset_head_name');
        $this->migrator->deleteIfExists('general.asset_head_nip');
        $this->migrator->deleteIfExists('general.asset_code_prefix');
        $this->migrator->deleteIfExists('general.public_qr_enabled');
        $this->migrator->deleteIfExists('general.public_damage_report_enabled');
    }
};