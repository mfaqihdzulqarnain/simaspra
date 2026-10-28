<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Enums\UnitType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use App\Models\Unit;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Unit')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Unit')
                        ->helperText('Singkat dan unik. Contoh: D3KEB')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                    Select::make('type')
                        ->label('Jenis')
                        ->options(UnitType::class)
                        ->default(UnitType::Unit)
                        ->required()
                        ->native(false),

                    TextInput::make('name')
                        ->label('Nama Unit')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    Select::make('parent_id')
                        ->label('Induk')
                        ->helperText('Kosongkan bila unit ini berdiri sendiri.')
                        ->relationship('parent', 'name', fn ($query, ?Unit $record) => $record
                            ? $query->whereKeyNot($record->getKey())
                            : $query)
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('head_user_id')
                        ->label('Pimpinan Unit')
                        ->relationship('headUser', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),
                ]),

            Section::make('Kontak')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('phone')->label('Telepon')->tel()->maxLength(20),
                    TextInput::make('email')->label('Email')->email()->maxLength(120),

                    Toggle::make('is_active')
                        ->label('Unit Aktif')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}