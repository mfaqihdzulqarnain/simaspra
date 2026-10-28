<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            // Biaya sewa pihak luar; dikunci saat reservasi disetujui
            $table->decimal('fee_amount', 15, 2)->nullable()->after('notes');
            $table->foreignId('rental_decree_id')->nullable()->after('fee_amount')
                ->constrained()->nullOnDelete();
            $table->json('fee_breakdown')->nullable()->after('rental_decree_id');

            $table->string('payment_status', 20)->nullable()->after('fee_breakdown');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->foreignId('payment_verified_by')->nullable()->after('paid_at')
                ->constrained('users')->nullOnDelete();
            $table->text('payment_note')->nullable()->after('payment_verified_by');

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropConstrainedForeignId('payment_verified_by');
            $table->dropConstrainedForeignId('rental_decree_id');
            $table->dropColumn(['fee_amount', 'fee_breakdown', 'payment_status', 'paid_at', 'payment_note']);
        });
    }
};
