<?php

namespace App\Filament\Resources\DamageReports;

// use App\Filament\Resources\DamageReports\Pages\CreateDamageReport;
// use App\Filament\Resources\DamageReports\Pages\EditDamageReport;
use App\Filament\Resources\DamageReports\Pages\ListDamageReports;
use App\Filament\Resources\DamageReports\Schemas\DamageReportForm;
use App\Filament\Resources\DamageReports\Tables\DamageReportsTable;
use App\Models\DamageReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DamageReportResource extends Resource
{
    protected static ?string $model = DamageReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Pemeliharaan';

    protected static ?string $navigationLabel = 'Laporan Kerusakan';

    protected static ?string $modelLabel = 'Laporan Kerusakan';

    protected static ?string $pluralModelLabel = 'Laporan Kerusakan';

    protected static ?int $navigationSort = 10;

    /** Laporan masuk lewat scan QR, bukan diketik di panel */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('is_processed', false)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return DamageReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DamageReportsTable::configure($table);
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
            'index' => ListDamageReports::route('/'),
            // 'create' => CreateDamageReport::route('/create'),
            // 'edit' => EditDamageReport::route('/{record}/edit'),
        ];
    }
}