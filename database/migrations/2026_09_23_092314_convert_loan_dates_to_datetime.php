<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('start_date', 'start_at');
            $table->renameColumn('end_date', 'end_at');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dateTime('start_at')->change();
            $table->dateTime('end_at')->change();
        });

        // Data lama hanya bertanggal. Tanpa ini, pinjaman lama langsung
        // dianggap terlambat sejak pukul 00.00 di hari jatuh temponya.
        DB::table('loans')
            ->whereRaw('TIME(end_at) = ?', ['00:00:00'])
            ->update(['end_at' => DB::raw("TIMESTAMP(DATE(end_at), '23:59:59')")]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('start_at')->change();
            $table->date('end_at')->change();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->renameColumn('start_at', 'start_date');
            $table->renameColumn('end_at', 'end_date');
        });
    }
};