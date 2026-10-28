<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.theme_preset', 'navy');
        $this->migrator->add('general.hero_tagline', 'Sistem Informasi Manajemen Sarana dan Prasarana');
        $this->migrator->add('general.show_asset_value', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.theme_preset');
        $this->migrator->deleteIfExists('general.hero_tagline');
        $this->migrator->deleteIfExists('general.show_asset_value');
    }
};