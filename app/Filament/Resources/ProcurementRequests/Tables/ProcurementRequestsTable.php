<?php

namespace App\Filament\Resources\ProcurementRequests\Tables;

use App\Enums\PriorityLevel;
use App\Enums\RequestStatus;
use App\Models\ProcurementRequest;
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

class ProcurementRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'unit:id,name',
                'requester:id,name',
            ]))
            ->columns([
                TextColumn::make('request_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->placeholder('(draf)'),

                TextColumn::make('unit.name')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('purpose')
                    ->label('Tujuan')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('total_estimated')
                    ->label('Perkiraan Biaya')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->sortable(),

                TextColumn::make('priority')->label('Prioritas')->badge()->sortable()->toggleable(),

                TextColumn::make('status')->label('Status')->badge()->sortable(),

                TextColumn::make('submitted_at')
                    ->label('Diajukan')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(RequestStatus::class),
                SelectFilter::make('priority')->label('Prioritas')->options(PriorityLevel::class),

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
                            $user?->hasRole('wadir') => $query->where('status', RequestStatus::MenungguWadir),
                            $user?->hasAnyRole(['kabag_sarpras', 'super_admin']) => $query
                                ->where('status', RequestStatus::VerifikasiSarpras),
                            default => $query->whereIn('status', [
                                RequestStatus::Draf->value,
                                RequestStatus::Revisi->value,
                            ]),
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->label('')
                    ->visible(fn (ProcurementRequest $record) => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada pengajuan')
            ->emptyStateDescription('Buat pengajuan kebutuhan barang untuk unit Anda.');
    }
}
