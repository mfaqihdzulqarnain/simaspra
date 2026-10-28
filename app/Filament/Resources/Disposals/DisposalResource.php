<?php

namespace App\Filament\Resources\Disposals;

use App\Enums\DisposalStatus;
use App\Filament\Resources\Disposals\Pages\CreateDisposal;
use App\Filament\Resources\Disposals\Pages\EditDisposal;
use App\Filament\Resources\Disposals\Pages\ListDisposals;
use App\Filament\Resources\Disposals\Pages\ViewDisposal;
use App\Filament\Resources\Disposals\Schemas\DisposalForm;
use App\Filament\Resources\Disposals\Schemas\DisposalInfolist;
use App\Filament\Resources\Disposals\Tables\DisposalsTable;
use App\Models\Disposal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DisposalResource extends Resource
{
    protected static ?string $model = Disposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Penghapusan Aset';

    protected static ?string $modelLabel = 'Penghapusan';

    protected static ?string $pluralModelLabel = 'Penghapusan Aset';

    protected static ?int $navigationSort = 18;

    protected static ?string $recordTitleAttribute = 'disposal_number';

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $count = match (true) {
            $user->hasRole('wadir') => static::getModel()::query()
                ->where('status', DisposalStatus::MenungguWadir)->count(),
            $user->hasAnyRole(['kabag_sarpras', 'super_admin']) => static::getModel()::query()
                ->whereIn('status', [
                    DisposalStatus::Usulan->value,
                    DisposalStatus::Pemeriksaan->value,
                    DisposalStatus::Disetujui->value,
                ])->count(),
            default => 0,
        };

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return DisposalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DisposalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisposalsTable::configure($table);
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
            'index' => ListDisposals::route('/'),
            'create' => CreateDisposal::route('/create'),
            'view' => ViewDisposal::route('/{record}'),
            'edit' => EditDisposal::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}