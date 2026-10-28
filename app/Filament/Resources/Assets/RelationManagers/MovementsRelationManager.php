<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Riwayat Mutasi';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'fromRoom:id,name',
                'toRoom:id,name',
                'fromUnit:id,name',
                'toUnit:id,name',
                'creator:id,name',
            ]))
            ->columns([
                TextColumn::make('moved_at')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('fromRoom.name')->label('Dari Ruang')->placeholder('—'),
                TextColumn::make('toRoom.name')->label('Ke Ruang')->placeholder('—'),
                TextColumn::make('reason')->label('Alasan')->wrap(),
                TextColumn::make('creator.name')->label('Oleh')->placeholder('—'),
            ])
            ->defaultSort('moved_at', 'desc')
            ->emptyStateHeading('Belum ada mutasi');
    }
}
