<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add(
            'general.reminder_template',
            "Assalamu'alaikum {nama},\n\n"
            . "Peminjaman alat berikut telah melewati batas pengembalian:\n"
            . "Nomor: {nomor}\n"
            . "Alat: {alat}\n"
            . "Lab: {lab}\n"
            . "Batas kembali: {batas}\n"
            . "Keterlambatan: {terlambat}\n\n"
            . "Mohon segera dikembalikan ke laboratorium. Terima kasih.\n\n"
            . "{instansi}"
        );
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('general.reminder_template');
    }
};