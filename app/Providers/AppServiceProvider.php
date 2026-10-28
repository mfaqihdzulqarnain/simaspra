<?php

namespace App\Providers;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use App\Models\Asset;
use App\Services\StatsService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Asset::saved(fn () => StatsService::flush());
        Asset::deleted(fn () => StatsService::flush());
        Password::defaults(fn () => Password::min(8)->uncompromised());
        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));
        setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'id');
    }
}