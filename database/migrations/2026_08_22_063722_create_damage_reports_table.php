<?php

use App\Enums\DamageSeverity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_contact', 60)->nullable();
            $table->text('description');
            $table->string('severity', 20)->default(DamageSeverity::Sedang->value);
            $table->timestamp('reported_at');
            $table->boolean('is_processed')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['is_processed', 'reported_at']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};