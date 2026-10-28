<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_consumable_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumable_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('quantity');            // dipinjam
            $table->unsignedInteger('returned_quantity')->nullable();
            $table->unsignedInteger('damaged_quantity')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['loan_id', 'consumable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_consumable_items');
    }
};