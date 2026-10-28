<?php

namespace App\Filament\Resources\AssetCategories\Tables;

use App\Models\AssetCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['parent:id,name']))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('medium'),

                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AssetCategory $record) => $record->parent?->name),

                TextColumn::make('useful_life_years')
                    ->label('Masa Manfaat')
                    ->suffix(' th')
                    ->alignCenter()
                    ->sortable(),

                IconColumn::make('requires_calibration')
                    ->label('Kalibrasi')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('assets_count')
                    ->label('Jumlah Aset')
                    ->counts('assets')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('requires_calibration')
                    ->label('Wajib Kalibrasi')
                    ->placeholder('Semua'),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make()->label(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada kategori')
            ->emptyStateDescription('Kategori menentukan kode aset dan masa penyusutan.');
    }
}