<?php

namespace App\Filament\Resources\ProcurementRequests\Schemas;

use App\Models\ProcurementRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProcurementRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('request_number'),
                TextEntry::make('unit.name')
                    ->label('Unit'),
                TextEntry::make('requested_by')
                    ->numeric(),
                TextEntry::make('budget_year')
                    ->numeric(),
                TextEntry::make('priority')
                    ->badge(),
                TextEntry::make('needed_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('purpose')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('total_estimated')
                    ->numeric(),
                TextEntry::make('submitted_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('verified_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('verification_note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('rejection_reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (ProcurementRequest $record): bool => $record->trashed()),
            ]);
    }
}
