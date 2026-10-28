<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')
                ->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('specification')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('unit_of_measure', 20)->default('unit');
            $table->decimal('estimated_unit_price', 15, 2)->default(0);
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->unsignedInteger('approved_quantity')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_request_items');
    }
};