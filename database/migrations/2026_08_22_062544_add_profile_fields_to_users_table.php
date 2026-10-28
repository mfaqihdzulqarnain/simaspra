<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
            $table->string('nip', 30)->nullable()->unique()->after('name');
            $table->string('position')->nullable()->after('nip');
            $table->string('phone', 20)->nullable()->after('position');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn([
                'unit_id', 'nip', 'position', 'phone', 'is_active', 'last_login_at',
            ]);
        });
    }
};