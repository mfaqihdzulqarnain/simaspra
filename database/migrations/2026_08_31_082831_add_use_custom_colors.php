<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.use_custom_colors', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.use_custom_colors');
    }
};