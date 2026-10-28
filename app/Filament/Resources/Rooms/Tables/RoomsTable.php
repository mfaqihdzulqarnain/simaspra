<?php

namespace App\Filament\Resources\Rooms\Tables;

use App\Filament\Pages\JadwalRuang;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'building:id,name',
                'unit:id,name',
                'picUser:id,name',
                'laboratory:id,name'
            ]))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('medium'),

                TextColumn::make('name')
                    ->label('Nama Ruang')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Room $record) => $record->building?->name),

                TextColumn::make('floor')->label('Lantai')->alignCenter()->sortable(),

                TextColumn::make('unit.name')->label('Unit')->placeholder('—')->toggleable(),

                TextColumn::make('laboratory.name')->label('Lab')->placeholder('—')->toggleable(),

                TextColumn::make('picUser.name')->label('PJ Ruang')->placeholder('—')->toggleable(),

                TextColumn::make('assets_count')
                    ->label('Aset')
                    ->counts('assets')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                IconColumn::make('is_bookable')
                    ->label('Reservasi')
                    ->boolean()
                    ->trueIcon('heroicon-o-calendar-days')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('building_id')
                    ->label('Gedung')
                    ->relationship('building', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_bookable')
                    ->label('Bisa Dipesan'),
            ])
            ->defaultSort('code')
            ->recordActions([
                Action::make('jadwal')
                    ->label('Jadwal')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('gray')
                    ->visible(fn (Room $record) => $record->is_bookable && JadwalRuang::canAccess())
                    ->url(fn (Room $record) => JadwalRuang::getUrl(['ruang' => $record->getKey()])),

                ActionGroup::make([
                    Action::make('halaman_ruang')
                        ->label('Buka Halaman Ruang')
                        ->icon(Heroicon::OutlinedGlobeAlt)
                        ->url(fn (Room $record) => $record->public_code ? $record->public_url : null)
                        ->disabled(fn (Room $record) => blank($record->public_code))
                        ->openUrlInNewTab(),

                    Action::make('cetak_qr_ruang')
                        ->label('Cetak QR Ruang')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->url(fn (Room $record) => $record->public_code ? route('room.qr.print', $record) : null)
                        ->disabled(fn (Room $record) => blank($record->public_code))
                        ->openUrlInNewTab(),
                ])
                    ->label('QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->button()
                    ->size('sm'),

                Action::make('cetak_kir')
                    ->label('Cetak KIR')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Room $record) => $record->public_code
                        ? route('kir.print', $record)
                        : null)
                    ->disabled(fn (Room $record) => blank($record->public_code))
                    ->openUrlInNewTab(),
                EditAction::make()->label(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Action::make('cetak_kir')
                    //     ->label('Cetak KIR')
                    //     ->icon(Heroicon::OutlinedPrinter)
                    //     ->color('gray')
                    //     ->url(fn (Room $record) => route('kir.print', $record))
                    //     ->openUrlInNewTab(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada ruangan')
            ->emptyStateDescription('Tambahkan ruangan agar aset bisa ditempatkan.');
    }
}