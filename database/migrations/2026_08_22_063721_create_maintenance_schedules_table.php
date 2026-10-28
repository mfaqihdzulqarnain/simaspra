<?php

use App\Enums\WorkOrderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->nullable()
                ->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->nullable()
                ->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type', 20)->default(WorkOrderType::Preventif->value);
            $table->unsignedTinyInteger('interval_months');
            $table->date('last_done_at')->nullable();
            $table->date('next_due_at');
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['next_due_at', 'is_active']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};