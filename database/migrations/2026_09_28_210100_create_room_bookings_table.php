<?php

use App\Enums\BookingStatus;
use App\Enums\BorrowerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 30)->unique();

            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('booking_type', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('attendees')->nullable();

            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Pemesan: akun internal, atau pihak luar yang dicatat petugas
            $table->string('borrower_type', 20)->default(BorrowerType::Internal->value);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('borrower_name');
            $table->string('borrower_institution')->nullable();
            $table->string('borrower_phone', 30)->nullable();
            $table->string('borrower_email')->nullable();

            $table->string('status', 20)->default(BookingStatus::Diajukan->value);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Cek bentrok: ruang dan irisan waktu
            $table->index(['room_id', 'start_at', 'end_at']);
            $table->index(['status', 'start_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
    }
};
