<?php

namespace App\Filament\Resources\ProcurementRequests\Schemas;

use App\Enums\PriorityLevel;
// use App\Enums\RequestStatus;
use Filament\Forms\Components\DatePicker;
// use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ProcurementRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Pengajuan')
                ->columns(2)
                ->schema([
                    Select::make('unit_id')
                        ->label('Unit Pengaju')
                        ->relationship('unit', 'name')
                        ->default(fn () => Auth::user()?->unit_id)
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->disabled(fn () => ! Auth::user()?->canSeeAllUnits())
                        ->dehydrated(),

                    TextInput::make('budget_year')
                        ->label('Tahun Anggaran')
                        ->numeric()
                        ->minValue(2020)
                        ->maxValue((int) now()->addYears(2)->format('Y'))
                        ->default((int) now()->format('Y'))
                        ->required(),

                    Select::make('priority')
                        ->label('Prioritas')
                        ->options(PriorityLevel::class)
                        ->default(PriorityLevel::Sedang)
                        ->required()
                        ->native(false),

                    DatePicker::make('needed_at')
                        ->label('Dibutuhkan Sebelum')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(now()),

                    Textarea::make('purpose')
                        ->label('Tujuan / Justifikasi')
                        ->helperText('Jelaskan mengapa barang ini dibutuhkan.')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                ]),

            Section::make('Daftar Barang')
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->relationship()
                        ->columns(4)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel('Tambah Barang')
                        ->schema([
                            Select::make('asset_category_id')
                                ->label('Kategori')
                                ->relationship('category', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->columnSpan(2),

                            TextInput::make('name')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(150)
                                ->columnSpan(2),

                            TextInput::make('quantity')
                                ->label('Jumlah')
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->required(),

                            TextInput::make('unit_of_measure')
                                ->label('Satuan')
                                ->default('unit')
                                ->required()
                                ->maxLength(20),

                            TextInput::make('estimated_unit_price')
                                ->label('Perkiraan Harga Satuan')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->prefix('Rp')
                                ->columnSpan(2),

                            Textarea::make('specification')
                                ->label('Spesifikasi')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }
}