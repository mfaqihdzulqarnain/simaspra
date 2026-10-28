<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Asset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('uuid')
                    ->label('UUID'),
                TextEntry::make('public_code'),
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('asset_category_id')
                    ->numeric(),
                TextEntry::make('unit.name')
                    ->label('Unit'),
                TextEntry::make('room.name')
                    ->label('Room')
                    ->placeholder('-'),
                TextEntry::make('vendor.name')
                    ->label('Vendor')
                    ->placeholder('-'),
                TextEntry::make('contract.title')
                    ->label('Contract')
                    ->placeholder('-'),
                TextEntry::make('procurement_request_item_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('brand')
                    ->placeholder('-'),
                TextEntry::make('model')
                    ->placeholder('-'),
                TextEntry::make('serial_number')
                    ->placeholder('-'),
                TextEntry::make('year_acquired')
                    ->numeric(),
                TextEntry::make('acquisition_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('acquisition_source')
                    ->badge(),
                TextEntry::make('acquisition_value')
                    ->numeric(),
                TextEntry::make('useful_life_years')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('condition')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('warranty_until')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('calibration_due_at')
                    ->date()
                    ->placeholder('-'),
                IconEntry::make('is_loanable')
                    ->boolean(),
                TextEntry::make('label_printed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('label_print_count')
                    ->numeric(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Asset $record): bool => $record->trashed()),
            ]);
    }
}
