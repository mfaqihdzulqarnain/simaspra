<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan kerusakan bisa berasal dari alat massal (tanpa kode aset)
 * dan bisa ditautkan ke peminjaman yang mengungkapkannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->change();

            $table->foreignId('consumable_id')->nullable()->after('asset_id')
                ->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->nullable()->after('consumable_id');
            $table->foreignId('loan_id')->nullable()->after('quantity')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('damage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
            $table->dropColumn('quantity');
            $table->dropConstrainedForeignId('consumable_id');
        });
    }
};