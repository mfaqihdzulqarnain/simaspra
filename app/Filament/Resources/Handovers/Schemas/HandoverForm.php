<?php

namespace App\Filament\Resources\Handovers\Schemas;

use App\Enums\HandoverType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class HandoverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->required(),
                Select::make('type')
                    ->options(HandoverType::class)
                    ->required(),
                TextInput::make('reference_type'),
                TextInput::make('reference_id')
                    ->numeric(),
                Select::make('from_user_id')
                    ->relationship('fromUser', 'name'),
                Select::make('to_user_id')
                    ->relationship('toUser', 'name'),
                Select::make('unit_id')
                    ->relationship('unit', 'name'),
                DatePicker::make('handover_date')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}
