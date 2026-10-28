<?php

use App\Enums\OpnameStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('unit_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('building_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('status', 20)->default(OpnameStatus::Draf->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};