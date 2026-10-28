<?php

namespace App\Filament\Resources\MaintenanceSchedules\Tables;

use App\Enums\WorkOrderType;
use App\Models\MaintenanceSchedule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'asset:id,code,name',
                'category:id,name',
                'assignee:id,name',
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Pekerjaan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (MaintenanceSchedule $record) => $record->asset
                        ? $record->asset->code.' — '.$record->asset->name
                        : 'Kategori: '.($record->category?->name ?? '—')),

                TextColumn::make('type')->label('Jenis')->badge()->sortable(),

                TextColumn::make('interval_months')
                    ->label('Interval')
                    ->suffix(' bln')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('last_done_at')->label('Terakhir')->date('d/m/Y')->placeholder('—')->toggleable(),

                TextColumn::make('next_due_at')
                    ->label('Jatuh Tempo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn (MaintenanceSchedule $record) => match (true) {
                        ! $record->is_active => 'gray',
                        $record->is_overdue => 'danger',
                        $record->days_until_due <= 7 => 'warning',
                        default => 'success',
                    })
                    ->description(fn (MaintenanceSchedule $record) => match (true) {
                        $record->is_overdue => 'Terlambat '.abs($record->days_until_due).' hari',
                        default => 'Sisa '.$record->days_until_due.' hari',
                    }),

                TextColumn::make('assignee.name')->label('Teknisi')->placeholder('—')->toggleable(),

                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(WorkOrderType::class),

                Filter::make('jatuh_tempo')
                    ->label('Jatuh Tempo (30 hari)')
                    ->query(fn (Builder $query) => $query->due(30)),

                Filter::make('terlambat')
                    ->label('Terlambat')
                    ->query(fn (Builder $query) => $query->overdue()),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->defaultSort('next_due_at')
            ->recordActions([
                EditAction::make()->label(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada jadwal pemeliharaan')
            ->emptyStateDescription('Buat jadwal preventif agar sistem mengingatkan sebelum aset rusak.');
    }
}
