<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('number', 60)->unique();
            // pengadaan | servis | sewa
            $table->string('type', 20)->default('pengadaan');
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->unsignedSmallInteger('reminder_days')->default(30);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('end_date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};