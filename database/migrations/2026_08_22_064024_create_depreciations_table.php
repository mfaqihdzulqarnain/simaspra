<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->decimal('opening_value', 15, 2);
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_value', 15, 2);
            $table->decimal('book_value', 15, 2);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['asset_id', 'period_year']);
            $table->index('period_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciations');
    }
};