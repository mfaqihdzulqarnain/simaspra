<?php

namespace App\Filament\Resources\RentalDecrees\Schemas;

use App\Enums\RateUnit;
use App\Models\Room;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RentalDecreeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Surat Keputusan')
                ->columns(2)
                ->schema([
                    TextInput::make('number')
                        ->label('Nomor SK')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('title')
                        ->label('Perihal')
                        ->placeholder('Contoh: Tarif Sewa Fasilitas Kampus')
                        ->required()
                        ->maxLength(255),

                    DatePicker::make('decree_date')
                        ->label('Tanggal SK')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('effective_from')
                        ->label('Berlaku Mulai')
                        ->helperText('Reservasi yang disetujui sejak tanggal ini memakai tarif SK ini.')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    SpatieMediaLibraryFileUpload::make('dokumen')
                        ->label('Berkas SK (PDF)')
                        ->helperText('Hanya PDF, maksimal 5 MB.')
                        ->collection('dokumen')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(5120)
                        ->preserveFilenames()
                        ->openable()
                        ->downloadable()
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Tarif per Ruang')
                ->description('Tulis tarif sesuai SK. Untuk tiap hari acara, sistem memilih tarif termurah yang mencukupi lama acara hari itu. Hari persiapan dan beres-beres tidak dihitung, dan pelengkap sudah termasuk.')
                ->schema([
                    Repeater::make('rates')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah Tarif')
                        ->reorderable(false)
                        ->columns(12)
                        ->itemLabel(fn (array $state) => filled($state['room_id'] ?? null)
                            ? (Room::find($state['room_id'])?->name.' — '.($state['name'] ?? ''))
                            : null)
                        ->schema([
                            Select::make('room_id')
                                ->label('Ruang')
                                ->options(fn () => Room::query()->bookable()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->native(false)
                                ->required()
                                ->columnSpan(4)
                                ->helperText('Hanya ruang yang dibuka untuk reservasi.'),

                            Select::make('unit')
                                ->label('Satuan')
                                ->options(RateUnit::class)
                                ->default(RateUnit::Hari->value)
                                ->required()
                                ->native(false)
                                ->live()
                                ->columnSpan(3),

                            TextInput::make('name')
                                ->label('Nama Tarif')
                                ->placeholder(fn (Get $get) => static::isSesi($get) ? 'Setengah hari' : 'Satu hari')
                                ->required()
                                ->maxLength(100)
                                ->columnSpan(5),

                            TextInput::make('session_hours')
                                ->label('Batas Jam Sesi')
                                ->numeric()
                                ->minValue(0.5)
                                ->maxValue(24)
                                ->step(0.5)
                                ->suffix('jam')
                                ->required(fn (Get $get) => static::isSesi($get))
                                ->visible(fn (Get $get) => static::isSesi($get))
                                ->helperText('Acara sampai sekian jam per hari memakai tarif ini.')
                                ->columnSpan(4),

                            TextInput::make('price')
                                ->label('Harga')
                                ->prefix('Rp')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->columnSpan(4),

                            TextInput::make('notes')
                                ->label('Keterangan')
                                ->maxLength(255)
                                ->columnSpan(4),
                        ]),
                ]),
        ]);
    }

    protected static function isSesi(Get $get): bool
    {
        $nilai = $get('unit');

        return ($nilai instanceof RateUnit ? $nilai->value : $nilai) === RateUnit::Sesi->value;
    }
}
