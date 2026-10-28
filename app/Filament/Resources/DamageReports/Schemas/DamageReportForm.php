<?php

namespace App\Filament\Resources\DamageReports\Schemas;

use App\Enums\DamageSeverity;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DamageReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('asset_id')
                    ->relationship('asset', 'name')
                    ->required(),
                TextInput::make('reported_by')
                    ->numeric(),
                TextInput::make('reporter_name'),
                TextInput::make('reporter_contact'),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Select::make('severity')
                    ->options(DamageSeverity::class)
                    ->default('sedang')
                    ->required(),
                DateTimePicker::make('reported_at')
                    ->required(),
                Toggle::make('is_processed')
                    ->required(),
                TextInput::make('ip_address'),
            ]);
    }
}
