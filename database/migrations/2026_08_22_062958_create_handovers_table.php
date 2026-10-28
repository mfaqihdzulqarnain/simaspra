<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('type', 30);

            $table->nullableMorphs('reference');

            $table->foreignId('from_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->date('handover_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['type', 'handover_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};