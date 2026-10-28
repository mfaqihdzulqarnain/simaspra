<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // Izin "View:Dashboard" di menu Peran (Shield) menentukan siapa yang melihat isinya
    use HasPageShield {
        canAccess as protected shieldCanAccess;
    }

    /** Punya izin melihat beranda utama */
    public static function canView(): bool
    {
        return static::shieldCanAccess();
    }

    /**
     * Halaman ini adalah alamat utama panel (/asetadmin), jadi selalu boleh dibuka.
     * Yang tidak berizin dialihkan ke berandanya sendiri di mount(), bukan diberi 403.
     */
    public static function canAccess(): bool
    {
        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canView();
    }

    public function mount(): void
    {
        if (! static::canView()) {
            $this->redirect(Filament::getHomeUrl());
        }
    }

    public static function getNavigationLabel(): string
    {
        return 'Beranda';
    }

    public function getTitle(): string
    {
        return 'Beranda';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }
}