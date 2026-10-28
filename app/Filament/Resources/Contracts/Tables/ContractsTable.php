<?php

namespace App\Filament\Resources\Contracts\Tables;

// use Filament\Actions\BulkActionGroup;
// use Filament\Actions\DeleteBulkAction;
// use Filament\Actions\EditAction;
use App\Models\Contract;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vendor:id,name']))
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->sortable()->weight('medium'),

                TextColumn::make('title')
                    ->label('Perihal')
                    ->searchable()
                    ->limit(40)
                    ->description(fn (Contract $record) => $record->vendor?->name),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pengadaan' => 'Pengadaan',
                        'servis' => 'Servis',
                        'sewa' => 'Sewa',
                        default => $state,
                    }),

                TextColumn::make('value')
                    ->label('Nilai')
                    ->money('IDR')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('end_date')
                    ->label('Berakhir')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->badge()
                    ->color(fn (Contract $record) => match (true) {
                        $record->end_date === null => 'gray',
                        $record->is_expired => 'danger',
                        $record->days_remaining <= $record->reminder_days => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Contract $record) => match (true) {
                        $record->end_date === null => null,
                        $record->is_expired => 'Sudah berakhir',
                        default => 'Sisa '.$record->days_remaining.' hari',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options([
                        'pengadaan' => 'Pengadaan',
                        'servis' => 'Servis / Pemeliharaan',
                        'sewa' => 'Sewa',
                    ]),

                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('expiring')
                    ->label('Akan Berakhir')
                    ->query(fn (Builder $query) => $query->expiringSoon()),

                Filter::make('expired')
                    ->label('Sudah Berakhir')
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('end_date')
                        ->where('end_date', '<', now()->toDateString())),
            ])
            ->defaultSort('end_date')
            ->recordActions([
                EditAction::make()->label(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada kontrak')
            ->emptyStateDescription('Catat kontrak agar sistem mengingatkan sebelum garansi atau servis habis.');
    }
}
