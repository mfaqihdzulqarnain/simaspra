<?php

namespace App\Filament\Resources\AssetCategories\Schemas;

use App\Models\AssetCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Kategori')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Kategori')
                        ->helperText('Masuk ke kode aset. Contoh: LAP menghasilkan PA-26-LAP-0001')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->alphaNum()
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                    TextInput::make('name')
                        ->label('Nama Kategori')
                        ->required()
                        ->maxLength(150),

                    Select::make('parent_id')
                        ->label('Kategori Induk')
                        ->relationship('parent', 'name', fn ($query, ?AssetCategory $record) => $record
                            ? $query->whereKeyNot($record->getKey())
                            : $query)
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Toggle::make('is_active')
                        ->label('Kategori Aktif')
                        ->default(true),

                    Textarea::make('description')
                        ->label('Keterangan')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Penyusutan')
                ->description('Dipakai menghitung nilai buku aset.')
                ->columns(2)
                ->schema([
                    TextInput::make('useful_life_years')
                        ->label('Masa Manfaat (tahun)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(4)
                        ->required(),

                    TextInput::make('residual_percent')
                        ->label('Nilai Residu (%)')
                        ->helperText('Sisa nilai setelah masa manfaat habis. Umumnya 0.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->suffix('%'),
                ]),

            Section::make('Kalibrasi')
                ->description('Untuk alat laboratorium yang wajib dikalibrasi berkala.')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Toggle::make('requires_calibration')
                        ->label('Wajib Kalibrasi')
                        ->live(),

                    TextInput::make('calibration_interval_months')
                        ->label('Interval Kalibrasi (bulan)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120)
                        ->default(12)
                        ->visible(fn ($get) => $get('requires_calibration'))
                        ->required(fn ($get) => $get('requires_calibration')),
                ]),
        ]);
    }
}