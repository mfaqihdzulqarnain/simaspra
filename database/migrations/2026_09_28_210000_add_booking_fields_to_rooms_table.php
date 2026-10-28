<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Ruang yang bisa dipesan lewat Reservasi Ruang
            $table->boolean('is_bookable')->default(false)->after('is_active');

            // Jenis kegiatan yang diizinkan: perkuliahan, rapat, acara
            $table->json('booking_types')->nullable()->after('is_bookable');

            // Jeda persiapan dan beres-beres di antara dua pemakaian
            $table->unsignedSmallInteger('booking_buffer_minutes')->default(0)->after('booking_types');

            $table->index('is_bookable');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['is_bookable']);
            $table->dropColumn(['is_bookable', 'booking_types', 'booking_buffer_minutes']);
        });
    }
};
