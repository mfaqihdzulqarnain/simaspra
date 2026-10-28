<?php

use App\Enums\AcquisitionSource;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_code', 12)->unique();
            $table->string('code', 40)->unique();
            $table->string('name');

            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('procurement_request_item_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();

            $table->unsignedSmallInteger('year_acquired');
            $table->date('acquisition_date')->nullable();
            $table->string('acquisition_source', 30)
                ->default(AcquisitionSource::Pembelian->value);
            $table->decimal('acquisition_value', 15, 2)->default(0);
            $table->unsignedTinyInteger('useful_life_years')->nullable();

            $table->string('condition', 20)->default(AssetCondition::Baik->value);
            $table->string('status', 30)->default(AssetStatus::Aktif->value);

            $table->date('warranty_until')->nullable();
            $table->date('calibration_due_at')->nullable();
            $table->boolean('is_loanable')->default(false);

            $table->timestamp('label_printed_at')->nullable();
            $table->unsignedInteger('label_print_count')->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'condition']);
            $table->index(['unit_id', 'status']);
            $table->index('room_id');
            $table->index('asset_category_id');
            $table->index('year_acquired');
            $table->index('is_loanable');
            $table->index('warranty_until');
            $table->index('calibration_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};