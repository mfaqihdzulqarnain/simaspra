<?php

namespace App\Filament\Resources\Consumables\RelationManagers;

use App\Enums\StockMovementType;
use App\Models\ConsumableMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Kartu Stok';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'unit:id,name',
                'creator:id,name',
            ]))
            ->columns([
                TextColumn::make('moved_at')->label('Tanggal')->date('d/m/Y')->sortable(),

                TextColumn::make('type')->label('Jenis')->badge(),

                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->formatStateUsing(fn ($state, ConsumableMovement $record) => match ($record->type) {
                        StockMovementType::Masuk => '+'.rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','),
                        StockMovementType::Keluar => '−'.rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','),
                        StockMovementType::Penyesuaian => '='.rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','),
                    })
                    ->color(fn (ConsumableMovement $record) => $record->type->getColor())
                    ->alignRight(),

                TextColumn::make('stock_after')
                    ->label('Sisa')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','))
                    ->weight('medium')
                    ->alignRight(),

                TextColumn::make('unit.name')->label('Unit')->placeholder('—')->toggleable(),

                TextColumn::make('reference')->label('Referensi')->placeholder('—')->toggleable(),

                TextColumn::make('note')->label('Catatan')->limit(40)->placeholder('—')->wrap(),

                TextColumn::make('creator.name')->label('Petugas')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(StockMovementType::class),
            ])
            ->defaultSort('moved_at', 'desc')
            ->emptyStateHeading('Belum ada transaksi')
            ->emptyStateDescription('Gunakan tombol Masuk atau Keluar di daftar barang.');
    }
}