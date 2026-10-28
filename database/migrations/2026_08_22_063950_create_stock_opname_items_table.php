<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('unregistered_name')->nullable();

            $table->foreignId('expected_room_id')->nullable()
                ->constrained('rooms')->nullOnDelete();
            $table->foreignId('found_room_id')->nullable()
                ->constrained('rooms')->nullOnDelete();

            $table->string('status', 30);
            $table->string('condition_found', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('checked_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['stock_opname_id', 'status']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};