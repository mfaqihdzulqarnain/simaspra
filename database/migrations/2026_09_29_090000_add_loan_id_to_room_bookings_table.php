<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            // Peminjaman pelengkap (kursi, sound, proyektor) yang menyertai reservasi
            $table->foreignId('loan_id')->nullable()->after('room_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
    }
};
