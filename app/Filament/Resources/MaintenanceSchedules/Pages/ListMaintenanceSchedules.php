<?php

namespace App\Filament\Resources\MaintenanceSchedules\Pages;

use App\Filament\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMaintenanceSchedules extends ListRecords
{
    protected static string $resource = MaintenanceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Jadwal')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'aktif' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true)),

            'jatuh_tempo' => Tab::make('Jatuh Tempo')
                ->modifyQueryUsing(fn (Builder $query) => $query->due(30))
                ->badge(fn () => static::getResource()::getModel()::query()->due(30)->count())
                ->badgeColor('warning'),

            'terlambat' => Tab::make('Terlambat')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue())
                ->badge(fn () => static::getResource()::getModel()::query()->overdue()->count())
                ->badgeColor('danger'),

            'semua' => Tab::make('Semua'),
        ];
    }
}