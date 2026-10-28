<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

// use App\Enums\OpnameStatus;
use Filament\Forms\Components\DatePicker;
// use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Periode Opname')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Kegiatan')
                        ->helperText('Contoh: Inventarisasi Semester Ganjil 2026')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    DatePicker::make('period_start')
                        ->label('Mulai')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()),

                    DatePicker::make('period_end')
                        ->label('Selesai')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->afterOrEqual('period_start')
                        ->default(now()->addDays(14)),
                ]),

            Section::make('Cakupan')
                ->description('Kosongkan keduanya untuk mencakup seluruh aset kampus.')
                ->columns(2)
                ->schema([
                    Select::make('unit_id')
                        ->label('Unit')
                        ->relationship('unit', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Batasi ke satu unit tertentu.'),

                    Select::make('building_id')
                        ->label('Gedung')
                        ->relationship('building', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Batasi ke satu gedung tertentu.'),

                    Textarea::make('note')
                        ->label('Catatan')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}