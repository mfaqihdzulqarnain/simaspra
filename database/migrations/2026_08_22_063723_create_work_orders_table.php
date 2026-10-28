<?php

use App\Enums\PriorityLevel;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('damage_report_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('maintenance_schedule_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('type', 20)->default(WorkOrderType::Korektif->value);
            $table->string('priority', 20)->default(PriorityLevel::Sedang->value);
            $table->text('description');

            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->date('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->string('status', 20)->default(WorkOrderStatus::Dilaporkan->value);
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('condition_after', 20)->nullable();
            $table->text('result_note')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['asset_id', 'status']);
            $table->index('scheduled_at');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};