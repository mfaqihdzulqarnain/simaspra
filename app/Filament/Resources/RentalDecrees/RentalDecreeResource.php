<?php

namespace App\Filament\Resources\RentalDecrees;

use App\Filament\Resources\RentalDecrees\Pages\CreateRentalDecree;
use App\Filament\Resources\RentalDecrees\Pages\EditRentalDecree;
use App\Filament\Resources\RentalDecrees\Pages\ListRentalDecrees;
use App\Filament\Resources\RentalDecrees\Schemas\RentalDecreeForm;
use App\Filament\Resources\RentalDecrees\Tables\RentalDecreesTable;
use App\Models\RentalDecree;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/** SK biaya sewa ruang untuk pihak luar, beserta tarif per ruang */
class RentalDecreeResource extends Resource
{
    protected static ?string $model = RentalDecree::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Tarif Sewa';

    protected static ?string $modelLabel = 'SK Tarif Sewa';

    protected static ?string $pluralModelLabel = 'Tarif Sewa';

    protected static ?int $navigationSort = 35;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return RentalDecreeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentalDecreesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentalDecrees::route('/'),
            'create' => CreateRentalDecree::route('/create'),
            'edit' => EditRentalDecree::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
