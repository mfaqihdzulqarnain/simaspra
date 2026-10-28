<?php

namespace App\Filament\Resources\AssetMovements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AssetMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('asset_id')
                    ->relationship('asset', 'name')
                    ->required(),
                Select::make('from_unit_id')
                    ->relationship('fromUnit', 'name'),
                Select::make('to_unit_id')
                    ->relationship('toUnit', 'name'),
                Select::make('from_room_id')
                    ->relationship('fromRoom', 'name'),
                Select::make('to_room_id')
                    ->relationship('toRoom', 'name'),
                DatePicker::make('moved_at')
                    ->required(),
                Textarea::make('reason')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('document_number'),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}
