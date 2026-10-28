<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            $table->foreignId('from_unit_id')->nullable()
                ->constrained('units')->nullOnDelete();
            $table->foreignId('to_unit_id')->nullable()
                ->constrained('units')->nullOnDelete();
            $table->foreignId('from_room_id')->nullable()
                ->constrained('rooms')->nullOnDelete();
            $table->foreignId('to_room_id')->nullable()
                ->constrained('rooms')->nullOnDelete();

            $table->date('moved_at');
            $table->text('reason');
            $table->string('document_number', 60)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};