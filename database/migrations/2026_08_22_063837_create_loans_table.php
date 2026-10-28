<?php

use App\Enums\BorrowerType;
use App\Enums\LoanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 30)->unique();
            $table->string('borrower_type', 20)->default(BorrowerType::Internal->value);

            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('borrower_name');
            $table->string('borrower_institution')->nullable();
            $table->string('borrower_phone', 20);
            $table->string('borrower_email')->nullable();

            $table->text('purpose');
            $table->string('use_location')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('returned_at')->nullable();

            $table->string('status', 20)->default(LoanStatus::Diajukan->value);
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'end_date']);
            $table->index(['start_date', 'end_date']);
            $table->index('borrower_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};