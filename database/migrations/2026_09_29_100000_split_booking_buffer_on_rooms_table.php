<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jeda reservasi dipisah menjadi dua:
 * - persiapan sebelum acara (bisa berhari-hari, misalnya dekorasi aula)
 * - beres-beres sesudah acara
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('booking_setup_minutes')->default(0)->after('booking_types');
            $table->unsignedInteger('booking_cleanup_minutes')->default(0)->after('booking_setup_minutes');
        });

        // Jeda lama berlaku di kedua sisi
        DB::table('rooms')->update([
            'booking_setup_minutes' => DB::raw('booking_buffer_minutes'),
            'booking_cleanup_minutes' => DB::raw('booking_buffer_minutes'),
        ]);

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('booking_buffer_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('booking_buffer_minutes')->default(0)->after('booking_types');
        });

        DB::table('rooms')->update([
            'booking_buffer_minutes' => DB::raw('LEAST(GREATEST(booking_setup_minutes, booking_cleanup_minutes), 65535)'),
        ]);

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['booking_setup_minutes', 'booking_cleanup_minutes']);
        });
    }
};
