<?php

namespace App\Filament\Resources\Consumables\Pages;

use App\Enums\ConsumableType;
use App\Filament\Resources\Consumables\ConsumableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListConsumables extends ListRecords
{
    protected static string $resource = ConsumableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Barang')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        $model = static::getResource()::getModel();

        return [
            'semua' => Tab::make('Semua'),

            'habis_pakai' => Tab::make('Habis Pakai')
                ->modifyQueryUsing(fn (Builder $query) => $query->consumed()),

            'alat_massal' => Tab::make('Alat Massal')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('type', ConsumableType::AlatMassal)),

            'dipinjam' => Tab::make('Sedang Dipinjam')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('on_loan', '>', 0))
                ->badge(fn () => $model::where('on_loan', '>', 0)->count())
                ->badgeColor('info'),

            'menipis' => Tab::make('Stok Menipis')
                ->modifyQueryUsing(fn (Builder $query) => $query->lowStock())
                ->badge(fn () => $model::query()->lowStock()->count())
                ->badgeColor('danger'),
        ];
    }
}
