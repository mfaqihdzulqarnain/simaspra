<?php

namespace App\Filament\Resources\AssetMovements\Tables;

use App\Models\AssetMovement;
// use Filament\Actions\BulkActionGroup;
// use Filament\Actions\DeleteBulkAction;
// use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'asset:id,code,name',
                'fromUnit:id,name',
                'toUnit:id,name',
                'fromRoom:id,name',
                'toRoom:id,name',
                'creator:id,name',
            ]))
            ->columns([
                TextColumn::make('moved_at')->label('Tanggal')->date('d/m/Y')->sortable(),

                TextColumn::make('asset.code')
                    ->label('Kode Aset')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (AssetMovement $record) => $record->asset?->name),

                TextColumn::make('fromRoom.name')
                    ->label('Dari')
                    ->placeholder('—')
                    ->description(fn (AssetMovement $record) => $record->fromUnit?->name),

                TextColumn::make('toRoom.name')
                    ->label('Ke')
                    ->placeholder('—')
                    ->description(fn (AssetMovement $record) => $record->toUnit?->name),

                TextColumn::make('reason')->label('Alasan')->limit(40)->wrap()->toggleable(),

                TextColumn::make('document_number')
                    ->label('Berita Acara')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('creator.name')->label('Dicatat Oleh')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('to_unit_id')
                    ->label('Unit Tujuan')
                    ->relationship('toUnit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('to_room_id')
                    ->label('Ruang Tujuan')
                    ->relationship('toRoom', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('antar_unit')
                    ->label('Perpindahan Antar Unit')
                    ->query(fn (Builder $query) => $query
                        ->whereColumn('from_unit_id', '!=', 'to_unit_id')),
            ])
            ->defaultSort('moved_at', 'desc')
            ->emptyStateHeading('Belum ada mutasi')
            ->emptyStateDescription('Mutasi dicatat lewat tombol Mutasi di halaman Daftar Aset.');
    }
}
