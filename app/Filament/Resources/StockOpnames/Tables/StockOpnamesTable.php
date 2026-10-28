<?php

namespace App\Filament\Resources\StockOpnames\Tables;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'unit:id,name',
                'building:id,name',
                'creator:id,name',
            ]))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('medium'),

                TextColumn::make('name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->description(fn (StockOpname $record) => collect([
                        $record->unit?->name,
                        $record->building?->name,
                    ])->filter()->implode(' · ') ?: 'Seluruh kampus'),

                TextColumn::make('period_start')->label('Mulai')->date('d/m/Y')->sortable()->toggleable(),
                TextColumn::make('period_end')->label('Selesai')->date('d/m/Y')->sortable(),

                TextColumn::make('items_count')
                    ->label('Total Aset')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('progress')
                    ->label('Progres')
                    ->getStateUsing(fn (StockOpname $record) => $record->progress_percent.'%')
                    ->badge()
                    ->color(fn (StockOpname $record) => match (true) {
                        $record->progress_percent >= 100 => 'success',
                        $record->progress_percent >= 50 => 'info',
                        $record->progress_percent > 0 => 'warning',
                        default => 'gray',
                    })
                    ->alignCenter(),

                TextColumn::make('status')->label('Status')->badge()->sortable(),

                TextColumn::make('creator.name')->label('Dibuat Oleh')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(OpnameStatus::class),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('')
                    ->visible(fn (StockOpname $record) => $record->status === OpnameStatus::Draf),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada kegiatan opname')
            ->emptyStateDescription('Buat periode inventarisasi untuk memeriksa keberadaan dan kondisi aset.');
    }
}