<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.letterhead', null);
        $this->migrator->add('general.use_letterhead_image', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.letterhead');
        $this->migrator->deleteIfExists('general.use_letterhead_image');
    }
};