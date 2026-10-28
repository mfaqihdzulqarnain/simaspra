<?php

use App\Enums\DisposalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposals', function (Blueprint $table) {
            $table->id();
            $table->string('disposal_number', 30)->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();

            $table->string('reason', 30);
            $table->text('description');
            $table->string('status', 30)->default(DisposalStatus::Usulan->value);

            $table->foreignId('inspected_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->text('inspection_note')->nullable();

            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->string('method', 20)->nullable();
            $table->date('executed_at')->nullable();
            $table->text('execution_note')->nullable();
            $table->decimal('total_book_value', 15, 2)->default(0);
            $table->decimal('revenue_amount', 15, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposals');
    }
};