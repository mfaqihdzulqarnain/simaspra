<?php

namespace App\Filament\Resources\Buildings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Gedung')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Gedung')
                        ->helperText('Contoh: GDA')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true),

                    TextInput::make('name')
                        ->label('Nama Gedung')
                        ->required()
                        ->maxLength(150),

                    TextInput::make('floor_count')
                        ->label('Jumlah Lantai')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(1)
                        ->required(),

                    Toggle::make('is_active')
                        ->label('Gedung Aktif')
                        ->default(true),

                    Textarea::make('address')
                        ->label('Alamat')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}