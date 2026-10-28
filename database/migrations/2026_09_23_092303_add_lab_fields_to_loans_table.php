<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('laboratory_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();

            // NIM atau NIP saat meminjam, disalin agar tetap utuh bila akun berubah
            $table->string('borrower_identity', 30)->nullable()->after('borrower_name');

            $table->string('purpose_type', 20)->nullable()->after('purpose');
            $table->string('course_name')->nullable()->after('purpose_type');
            $table->foreignId('supervisor_id')->nullable()->after('course_name')
                ->constrained('users')->nullOnDelete();

            $table->index(['laboratory_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['laboratory_id', 'status']);
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropConstrainedForeignId('laboratory_id');
            $table->dropColumn(['borrower_identity', 'purpose_type', 'course_name']);
        });
    }
};