<?php

use App\Enums\RateUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per SK biaya sewa. PDF SK disimpan lewat MediaLibrary.
        Schema::create('rental_decrees', function (Blueprint $table) {
            $table->id();
            $table->string('number', 100);
            $table->string('title');
            $table->date('decree_date');
            $table->date('effective_from');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('effective_from');
        });

        // Tarif per ruang di dalam satu SK
        Schema::create('rental_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_decree_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('unit', 20)->default(RateUnit::Hari->value);
            $table->string('name', 100);
            // Batas jam untuk tarif sesi, misalnya setengah hari = 5 jam
            $table->decimal('session_hours', 4, 1)->nullable();
            $table->decimal('price', 15, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['rental_decree_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_rates');
        Schema::dropIfExists('rental_decrees');
    }
};
