<?php

namespace App\Filament\Resources\Handovers\Tables;

use App\Enums\HandoverType;
use App\Models\Handover;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class HandoversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'unit:id,name',
                'fromUser:id,name',
                'toUser:id,name',
            ]))
            ->columns([
                TextColumn::make('number')->label('Nomor BA')->searchable()->sortable()->weight('medium')->description(fn (Handover $record) => $record->type?->getLabel()),
                TextColumn::make('unit.name')->label('Tujuan Unit/Prodi')->placeholder('—')->sortable(),
                TextColumn::make('fromUser.name')->label('Yang Menyerahkan')->placeholder('—')->toggleable(),
                TextColumn::make('toUser.name')->label('Penerima')->placeholder('—'),

                // Dalam unit: aset kolektif dihitung sesuai jumlahnya
                TextColumn::make('items_sum_quantity')
                    ->label('Jumlah Aset')
                    ->sum('items', 'quantity')
                    ->default(0)
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('handover_date')->label('Tanggal')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(HandoverType::class),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('handover_date', 'desc')
            ->recordActions([
                Action::make('cetak')
                    ->label('Cetak')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Handover $record) => route('bast.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Berita Acara')
                        ->modalHeading('Yakin Hapus Berita Acara?')
                        ->modalDescription(fn (Collection $records) => $records->count()
                            . ' berita acara akan dihapus permanen beserta daftar asetnya. '
                            . 'Status dan kondisi aset tidak ikut berubah, dan nomor yang terhapus tidak dipakai ulang.')
                        ->modalSubmitActionLabel('Ya, hapus')
                        ->modalWidth('lg')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('akun_nama')
                                    ->label('Nama')
                                    ->default(fn () => auth()->user()?->name)
                                    ->readOnly()
                                    ->dehydrated(false),

                                TextInput::make('akun_email')
                                    ->label('Email')
                                    ->default(fn () => auth()->user()?->email)
                                    ->readOnly()
                                    ->dehydrated(false),
                            ]),

                            TextInput::make('password')
                                ->label('Kata sandi Anda')
                                ->helperText('Masukkan kata sandi akun yang sedang login untuk mengonfirmasi.')
                                ->password()
                                ->revealable()
                                ->required()
                                ->currentPassword()
                                ->validationMessages([
                                    'current_password' => 'Kata sandi tidak sesuai.',
                                ])
                                ->autocomplete('current-password')
                                ->dehydrated(false),
                        ]),
                ]),
            ])
            ->emptyStateHeading('Belum ada berita acara')
            ->emptyStateDescription('Berita acara dibuat otomatis saat penyaluran, peminjaman, atau penghapusan.');
    }
}
