<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();

            // Batas lama peminjaman, dalam jam
            $table->unsignedSmallInteger('student_max_hours')->default(8);
            $table->unsignedSmallInteger('lecturer_max_hours')->default(72);

            $table->boolean('requires_approval')->default(true);
            $table->text('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratories');
    }
};