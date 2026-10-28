<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedTinyInteger('step')->default(1);
            $table->string('role_name', 50);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->text('note')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('acted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};