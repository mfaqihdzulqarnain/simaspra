<?php

namespace App\Filament\Resources\Buildings\Tables;

// use Filament\Actions\BulkActionGroup;
// use Filament\Actions\DeleteBulkAction;
// use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('medium'),
                TextColumn::make('name')->label('Nama Gedung')->searchable()->sortable(),
                TextColumn::make('floor_count')->label('Lantai')->alignCenter()->sortable(),

                TextColumn::make('rooms_count')
                    ->label('Jumlah Ruang')
                    ->counts('rooms')
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
            ->defaultSort('code')
            ->recordActions([
                \Filament\Actions\EditAction::make()->label(''),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada gedung')
            ->emptyStateDescription('Daftarkan gedung sebelum menambahkan ruangan.');
    }
}