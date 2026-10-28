<?php

use App\Services\LoanService;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => MaintenanceService::generateDueWorkOrders(7))
    ->dailyAt('08:00')
    ->name('generate-preventive-work-orders')
    ->withoutOverlapping();

Schedule::call(fn () => LoanService::markOverdue())
    ->everyFifteenMinutes()
    ->name('mark-overdue-loans')
    ->withoutOverlapping();

// Proses antrean (notifikasi) tiap menit, lalu berhenti sebelum menit berikutnya
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
