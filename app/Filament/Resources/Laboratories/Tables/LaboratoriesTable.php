<?php

namespace App\Filament\Resources\Laboratories\Tables;

use App\Models\Laboratory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaboratoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'unit:id,name',
                'head:id,name',
                'staff:id,name',
            ]))
            ->columns([
                TextColumn::make('code')
                    ->label('Kode dan Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Laboratory $record) => $record->name),

                TextColumn::make('unit.name')->label('Unit')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('head.name')->label('Kepala Lab')->placeholder('—')->toggleable(),

                TextColumn::make('staff.name')
                    ->label('Laboran')
                    ->badge()
                    ->separator(',')
                    ->placeholder('Belum ditugaskan')
                    ->color(fn (?string $state) => $state ? 'primary' : 'warning'),

                TextColumn::make('rooms_count')
                    ->label('Ruang')
                    ->counts('rooms')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('assets_count')
                    ->label('Jumlah Alat')
                    ->counts('assets')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('student_max_hours')
                    ->label('Batas Pinjam Mhs')
                    ->suffix(' jam')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('lecturer_max_hours')
                    ->label('Batas Pinjam Dosen')
                    ->suffix(' jam')
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->filters([
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
            ->emptyStateHeading('Belum ada laboratorium')
            ->emptyStateDescription('Daftarkan lab, lalu tautkan ruangannya agar alat otomatis terkelompok.');
    }
}
