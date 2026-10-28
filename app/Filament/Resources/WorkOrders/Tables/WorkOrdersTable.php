<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use App\Enums\PriorityLevel;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\WorkOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
// use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class WorkOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'asset:id,code,name,acquisition_value',
                'assignee:id,name',
                'vendor:id,name',
            ]))
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->sortable()->weight('medium'),

                TextColumn::make('asset.code')
                    ->label('Aset')
                    ->searchable()
                    ->description(fn (WorkOrder $record) => $record->asset?->name),

                TextColumn::make('type')->label('Jenis')->badge()->sortable(),
                TextColumn::make('priority')->label('Prioritas')->badge()->sortable()->toggleable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),

                TextColumn::make('assignee.name')->label('Teknisi')->placeholder('Belum ditugaskan'),

                TextColumn::make('scheduled_at')
                    ->label('Rencana')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('cost')
                    ->label('Biaya')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(WorkOrderStatus::class),
                SelectFilter::make('type')->label('Jenis')->options(WorkOrderType::class),
                SelectFilter::make('priority')->label('Prioritas')->options(PriorityLevel::class),

                Filter::make('terbuka')
                    ->label('Masih Terbuka')
                    ->query(fn (Builder $query) => $query->open()),

                Filter::make('tugas_saya')
                    ->label('Ditugaskan ke Saya')
                    ->query(fn (Builder $query) => $query->where('assigned_to', Auth::id())),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada perintah kerja')
            ->emptyStateDescription('Perintah kerja dibuat dari laporan kerusakan atau jadwal pemeliharaan.');
    }
}
