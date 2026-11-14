<?php

namespace App\Providers\Filament;

use App\Filament\Pages\BerandaLaboran;
use App\Filament\Pages\BerandaSaya;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AssetIntakeChart;
use App\Filament\Widgets\ConditionChart;
use App\Filament\Widgets\RequestTrendChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UnitDistributionChart;
use App\Support\Sapra;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AsetadminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('asetadmin')
            ->path('asetadmin')
            ->login()
            ->profile()
            ->passwordReset()
            ->spa()
            ->brandName(fn () => Sapra::appName())
            // Blok merek: kotak logo + nama + institusi (resources/views/filament/brand.blade.php)
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(Sapra::favicon())
            ->colors([
                'primary' => Color::hex(Sapra::primaryColor()),
                'gray' => Color::Slate,
                'green' => Color::hex('#12B76A'),
            ])
            // Gaya panel: sidebar gelap ikut tema, tampilan padat, kartu angka
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.hooks.panel-style'),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): View => view('filament.auth.login-aside'),
                scopes: [\Filament\Auth\Pages\Login::class],
            )
            // Navigasi samping (bukan atas), bisa diciutkan di desktop
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->maxContentWidth(Width::Full)
            // Beranda awal sesuai hak akses
            ->homeUrl(fn (): string => match (true) {
                Dashboard::canView() => Dashboard::getUrl(),
                BerandaLaboran::canAccess() => BerandaLaboran::getUrl(),
                default => BerandaSaya::getUrl(),
            })
            ->userMenuItems([
                Action::make('halaman_depan')
                    ->label('Halaman Depan')
                    ->icon('heroicon-o-globe-alt')
                    ->url(fn (): string => url('/'), shouldOpenInNewTab: true),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                // Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                StatsOverview::class,
                RequestTrendChart::class,
                AssetIntakeChart::class,
                ConditionChart::class,
                UnitDistributionChart::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->navigationGroups([
                'Laboratorium',
                'Aset',
                'Transaksi',
                'Pemeliharaan',
                'Data Master',
                'Pengaturan',
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
