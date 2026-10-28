<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            // Aset tunggal selalu 1; aset kolektif bisa lebih dari 1
            $table->unsignedInteger('quantity')->default(1)->after('asset_id');

            // Diisi saat pengembalian aset kolektif
            $table->unsignedInteger('returned_quantity')->nullable()->after('condition_after');
            $table->unsignedInteger('damaged_quantity')->nullable()->after('returned_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'returned_quantity', 'damaged_quantity']);
        });
    }
};
