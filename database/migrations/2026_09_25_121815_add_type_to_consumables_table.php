<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->string('type', 20)->default('habis_pakai')->after('code');
            $table->foreignId('laboratory_id')->nullable()->after('storage_room_id')
                ->constrained()->nullOnDelete();

            // Jumlah yang sedang berada di tangan peminjam
            $table->unsignedInteger('on_loan')->default(0)->after('current_stock');

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropConstrainedForeignId('laboratory_id');
            $table->dropColumn(['type', 'on_loan']);
        });
    }
};