<?php

namespace App\Filament\Resources\Handovers;

// use App\Filament\Resources\Handovers\Pages\CreateHandover;
// use App\Filament\Resources\Handovers\Pages\EditHandover;
use App\Filament\Resources\Handovers\Pages\ListHandovers;
// use App\Filament\Resources\Handovers\Schemas\HandoverForm;
use App\Filament\Resources\Handovers\Tables\HandoversTable;
use App\Models\Handover;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HandoverResource extends Resource
{
   protected static ?string $model = Handover::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Berita Acara';

    protected static ?string $modelLabel = 'Berita Acara';

    protected static ?string $pluralModelLabel = 'Berita Acara';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'number';
    
    /** Dibuat lewat proses, bukan diketik manual */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return HandoversTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHandovers::route('/'),
            //'create' => CreateHandover::route('/create'),
            //'edit' => EditHandover::route('/{record}/edit'),
        ];
    }
}