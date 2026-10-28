<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            // Aset kolektif: jumlah menurut catatan dan jumlah yang ditemukan
            $table->unsignedInteger('expected_quantity')->nullable()->after('found_room_id');
            $table->unsignedInteger('found_quantity')->nullable()->after('expected_quantity');

            // Tindak lanjut selisih jumlah saat opname ditutup: sesuaikan / laporkan
            $table->string('quantity_follow_up', 20)->nullable()->after('found_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['expected_quantity', 'found_quantity', 'quantity_follow_up']);
        });
    }
};
