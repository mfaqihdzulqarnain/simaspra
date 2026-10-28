<?php

namespace App\Filament\Resources\Disposals\Schemas;

use App\Models\Disposal;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DisposalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('disposal_number'),
                TextEntry::make('unit.name')
                    ->label('Unit'),
                TextEntry::make('proposed_by')
                    ->numeric(),
                TextEntry::make('reason')
                    ->badge(),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('inspected_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('inspected_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('inspection_note')
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
                TextEntry::make('method')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('executed_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('execution_note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('total_book_value')
                    ->numeric(),
                TextEntry::make('revenue_amount')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Disposal $record): bool => $record->trashed()),
            ]);
    }
}
