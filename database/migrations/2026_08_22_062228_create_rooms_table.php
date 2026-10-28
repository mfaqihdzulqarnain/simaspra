<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('floor')->default(1);
            $table->decimal('area_m2', 8, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->foreignId('pic_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['building_id', 'floor']);
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};