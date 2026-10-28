<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Barang dan alat massal dimiliki unit/prodi, opsional dikelola laboratorium.
 * Data lama: unit diambil dari unit pengelola laboratoriumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('storage_room_id')
                ->constrained()->nullOnDelete();
        });

        DB::table('consumables')
            ->whereNull('unit_id')
            ->whereNotNull('laboratory_id')
            ->update([
                'unit_id' => DB::raw('(SELECT laboratories.unit_id FROM laboratories WHERE laboratories.id = consumables.laboratory_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};