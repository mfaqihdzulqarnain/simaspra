<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 20);
            $table->text('message');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['loan_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_reminders');
    }
};