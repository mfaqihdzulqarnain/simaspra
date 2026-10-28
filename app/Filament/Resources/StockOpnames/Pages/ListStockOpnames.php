<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Enums\OpnameStatus;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockOpnames extends ListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Kegiatan Opname')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            
            'berjalan' => Tab::make('Sedang Berjalan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OpnameStatus::Berjalan))
                ->badge(fn () => static::getResource()::getModel()::where('status', OpnameStatus::Berjalan)->count())
                ->badgeColor('info'),

            'draf' => Tab::make('Draf')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OpnameStatus::Draf)),

            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OpnameStatus::Selesai)),

        ];
    }
}