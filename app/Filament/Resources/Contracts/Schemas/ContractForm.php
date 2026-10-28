<?php

namespace App\Filament\Resources\Contracts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Kontrak')
                ->columns(2)
                ->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->relationship('vendor', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),

                    TextInput::make('number')
                        ->label('Nomor Kontrak')
                        ->required()
                        ->maxLength(60)
                        ->unique(ignoreRecord: true),

                    Select::make('type')
                        ->label('Jenis Kontrak')
                        ->options([
                            'pengadaan' => 'Pengadaan',
                            'servis' => 'Servis / Pemeliharaan',
                            'sewa' => 'Sewa',
                        ])
                        ->default('pengadaan')
                        ->required()
                        ->native(false),

                    TextInput::make('value')
                        ->label('Nilai Kontrak')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp'),

                    TextInput::make('title')
                        ->label('Judul / Perihal')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),
                ]),

            Section::make('Masa Berlaku')
                ->columns(3)
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('end_date')
                        ->label('Tanggal Berakhir')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->afterOrEqual('start_date'),

                    TextInput::make('reminder_days')
                        ->label('Ingatkan (hari sebelum)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->default(30),

                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}