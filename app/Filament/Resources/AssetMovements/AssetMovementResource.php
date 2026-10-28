<?php

namespace App\Filament\Resources\AssetMovements;

// use App\Filament\Resources\AssetMovements\Pages\CreateAssetMovement;
// use App\Filament\Resources\AssetMovements\Pages\EditAssetMovement;
use App\Filament\Resources\AssetMovements\Pages\ListAssetMovements;
use App\Filament\Resources\AssetMovements\Schemas\AssetMovementForm;
use App\Filament\Resources\AssetMovements\Tables\AssetMovementsTable;
use App\Models\AssetMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AssetMovementResource extends Resource
{
    protected static ?string $model = AssetMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Aset';

    protected static ?string $navigationLabel = 'Riwayat Mutasi';

    protected static ?string $modelLabel = 'Mutasi Aset';

    protected static ?string $pluralModelLabel = 'Riwayat Mutasi';

    protected static ?int $navigationSort = 30;

    /** Mutasi dibuat lewat aksi di Daftar Aset */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AssetMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetMovementsTable::configure($table);
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
            'index' => ListAssetMovements::route('/'),
            // 'create' => CreateAssetMovement::route('/create'),
            // 'edit' => EditAssetMovement::route('/{record}/edit'),
        ];
    }
}