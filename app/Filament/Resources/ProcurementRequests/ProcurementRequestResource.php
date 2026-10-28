<?php

namespace App\Filament\Resources\ProcurementRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\ProcurementRequests\Pages\CreateProcurementRequest;
use App\Filament\Resources\ProcurementRequests\Pages\EditProcurementRequest;
use App\Filament\Resources\ProcurementRequests\Pages\ListProcurementRequests;
use App\Filament\Resources\ProcurementRequests\Pages\ViewProcurementRequest;
use App\Filament\Resources\ProcurementRequests\Schemas\ProcurementRequestForm;
use App\Filament\Resources\ProcurementRequests\Schemas\ProcurementRequestInfolist;
use App\Filament\Resources\ProcurementRequests\Tables\ProcurementRequestsTable;
use App\Models\ProcurementRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ProcurementRequestResource extends Resource
{
    protected static ?string $model = ProcurementRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Pengajuan Aset';

    protected static ?string $modelLabel = 'Pengajuan';

    protected static ?string $pluralModelLabel = 'Pengajuan Aset';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'request_number';

    /** Badge jumlah yang menunggu tindakan saya */
    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $count = match (true) {
            $user->hasRole('wadir') => static::getModel()::query()
                ->where('status', RequestStatus::MenungguWadir)->count(),
            $user->hasAnyRole(['kabag_sarpras', 'super_admin']) => static::getModel()::query()
                ->where('status', RequestStatus::VerifikasiSarpras)->count(),
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
        return ProcurementRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProcurementRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcurementRequestsTable::configure($table);
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
            'index' => ListProcurementRequests::route('/'),
            'create' => CreateProcurementRequest::route('/create'),
            'view' => ViewProcurementRequest::route('/{record}'),
            'edit' => EditProcurementRequest::route('/{record}/edit'),
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