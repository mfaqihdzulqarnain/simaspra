<?php

namespace App\Filament\Resources\RentalDecrees\Tables;

use App\Models\RentalDecree;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RentalDecreesTable
{
    public static function configure(Table $table): Table
    {
        // Dihitung sekali per render, bukan per baris
        $berlaku = RentalDecree::current()?->getKey();

        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor SK')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (RentalDecree $record) => $record->title),

                TextColumn::make('decree_date')
                    ->label('Tanggal SK')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('effective_from')
                    ->label('Berlaku Mulai')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (RentalDecree $record) => match (true) {
                        $record->trashed() => 'Dihapus',
                        $record->getKey() === $berlaku => 'Berlaku',
                        $record->effective_from->isFuture() => 'Akan Berlaku',
                        default => 'Arsip',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Berlaku' => 'success',
                        'Akan Berlaku' => 'info',
                        'Dihapus' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('rates_count')
                    ->label('Jumlah Tarif')
                    ->counts('rates')
                    ->alignCenter(),

                TextColumn::make('dokumen')
                    ->label('Berkas')
                    ->state(fn (RentalDecree $record) => $record->documentUrl() ? 'Lihat PDF' : null)
                    ->url(fn (RentalDecree $record) => $record->documentUrl(), shouldOpenInNewTab: true)
                    ->icon(fn ($state) => $state ? 'heroicon-o-document-text' : null)
                    ->color('primary')
                    ->placeholder('Belum diunggah'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('effective_from', 'desc')
            ->recordActions([
                EditAction::make()->label(''),
            ])
            ->emptyStateHeading('Belum ada SK tarif sewa')
            ->emptyStateDescription('Tambahkan SK biaya sewa beserta berkas PDF-nya agar biaya reservasi pihak luar bisa dihitung.');
    }
}
