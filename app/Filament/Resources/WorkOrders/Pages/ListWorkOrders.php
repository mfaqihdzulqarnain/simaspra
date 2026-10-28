<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Enums\WorkOrderStatus;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Services\MaintenanceService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_preventif')
                ->label('Bangkitkan Jadwal Jatuh Tempo')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Bangkitkan Perintah Kerja Preventif')
                ->modalDescription('Sistem akan membuat perintah kerja untuk semua jadwal yang jatuh tempo dalam 7 hari ke depan. Jadwal yang sudah punya perintah kerja terbuka dilewati.')
                ->visible(fn () => Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function () {
                    $count = MaintenanceService::generateDueWorkOrders(7);

                    Notification::make()
                        ->success()
                        ->title($count > 0 ? "{$count} perintah kerja dibuat" : 'Tidak ada jadwal jatuh tempo')
                        ->send();
                }),

            CreateAction::make()
                ->label('Buat Perintah Kerja')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'terbuka' => Tab::make('Terbuka')
                ->modifyQueryUsing(fn (Builder $query) => $query->open())
                ->badge(fn () => static::getResource()::getModel()::query()->open()->count())
                ->badgeColor('warning'),

            'saya' => Tab::make('Tugas Saya')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('assigned_to', Auth::id())
                    ->open()),

            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', WorkOrderStatus::Selesai)),

            'semua' => Tab::make('Semua'),
        ];
    }
}