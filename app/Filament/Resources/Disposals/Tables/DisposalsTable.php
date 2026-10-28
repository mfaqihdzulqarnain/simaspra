<?php

namespace App\Filament\Resources\Disposals\Tables;

use App\Enums\DisposalMethod;
use App\Enums\DisposalReason;
use App\Enums\DisposalStatus;
use App\Models\Disposal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
// use Filament\Actions\ForceDeleteBulkAction;
// use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
// use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DisposalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'unit:id,name',
                'proposer:id,name',
            ]))
            ->columns([
                TextColumn::make('disposal_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('unit.name')->label('Unit')->searchable()->sortable(),

                TextColumn::make('reason')->label('Alasan')->badge()->sortable(),

                TextColumn::make('items_count')
                    ->label('Aset')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('total_book_value')
                    ->label('Nilai Buku')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')->label('Status')->badge()->sortable(),

                TextColumn::make('method')->label('Metode')->badge()->placeholder('—')->toggleable(),

                TextColumn::make('executed_at')
                    ->label('Dieksekusi')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(DisposalStatus::class),
                SelectFilter::make('reason')->label('Alasan')->options(DisposalReason::class),
                SelectFilter::make('method')->label('Metode')->options(DisposalMethod::class),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => Auth::user()?->canSeeAllUnits()),

                Filter::make('menunggu_saya')
                    ->label('Menunggu Tindakan Saya')
                    ->query(function (Builder $query) {
                        $user = Auth::user();

                        return match (true) {
                            $user?->hasRole('wadir') => $query->where('status', DisposalStatus::MenungguWadir),
                            $user?->hasAnyRole(['kabag_sarpras', 'super_admin']) => $query->whereIn('status', [
                                DisposalStatus::Usulan->value,
                                DisposalStatus::Pemeriksaan->value,
                                DisposalStatus::Disetujui->value,
                            ]),
                            default => $query->where('status', DisposalStatus::Usulan),
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('')
                    ->visible(fn (Disposal $record) => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada usulan penghapusan')
            ->emptyStateDescription('Usulkan penghapusan untuk aset yang rusak berat, hilang, atau tidak ekonomis diperbaiki.');
    }
}
