<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Models\Vendor;
// use Filament\Actions\BulkActionGroup;
// use Filament\Actions\DeleteBulkAction;
// use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('medium'),

                TextColumn::make('name')
                    ->label('Nama Vendor')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Vendor $record) => $record->contact_person),

                TextColumn::make('phone')->label('Telepon')->placeholder('—')->toggleable(),

                TextColumn::make('contracts_count')
                    ->label('Kontrak')
                    ->counts('contracts')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('assets_count')
                    ->label('Aset')
                    ->counts('assets')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->defaultSort('name')
            ->recordActions([
                \Filament\Actions\EditAction::make()->label(''),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada vendor')
            ->emptyStateDescription('Catat penyedia barang dan jasa untuk melacak garansi dan servis.');
    }
}