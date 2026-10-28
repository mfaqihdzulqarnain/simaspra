<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    protected static ?string $title = 'Riwayat Pemeliharaan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->weight('medium'),
                TextColumn::make('type')->label('Jenis')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('cost')->label('Biaya')->money('IDR')->placeholder('—'),
                TextColumn::make('finished_at')->label('Selesai')->date('d/m/Y')->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada pemeliharaan');
    }
}