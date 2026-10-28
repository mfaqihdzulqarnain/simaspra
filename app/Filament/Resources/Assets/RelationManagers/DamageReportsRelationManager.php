<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DamageReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'damageReports';

    protected static ?string $title = 'Laporan Kerusakan';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reporter:id,name']))
            ->columns([
                TextColumn::make('reported_at')->label('Tanggal')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('severity')->label('Tingkat')->badge(),
                TextColumn::make('description')->label('Keterangan')->wrap()->limit(60),
                TextColumn::make('reporter_label')->label('Pelapor'),
                IconColumn::make('is_processed')->label('Ditindak')->boolean()->alignCenter(),
            ])
            ->defaultSort('reported_at', 'desc')
            ->emptyStateHeading('Belum ada laporan kerusakan');
    }
}