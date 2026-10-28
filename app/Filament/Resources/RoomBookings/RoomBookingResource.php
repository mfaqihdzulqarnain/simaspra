<?php

namespace App\Filament\Resources\RoomBookings;

use App\Enums\BookingStatus;
use App\Filament\Pages\JadwalRuang;
use App\Filament\Resources\RoomBookings\Pages\CreateRoomBooking;
use App\Filament\Resources\RoomBookings\Pages\EditRoomBooking;
use App\Filament\Resources\RoomBookings\Pages\ListRoomBookings;
use App\Filament\Resources\RoomBookings\Pages\ViewRoomBooking;
use App\Filament\Resources\RoomBookings\Schemas\RoomBookingForm;
use App\Filament\Resources\RoomBookings\Tables\RoomBookingsTable;
use App\Models\RoomBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class RoomBookingResource extends Resource
{
    protected static ?string $model = RoomBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Reservasi Ruang';

    protected static ?string $modelLabel = 'Reservasi Ruang';

    protected static ?string $pluralModelLabel = 'Reservasi Ruang';

    protected static ?int $navigationSort = 16;

    protected static ?string $recordTitleAttribute = 'booking_number';

    /**
     * Menu Reservasi Ruang tetap tersorot saat halaman Jadwal Ruang terbuka,
     * karena keduanya satu menu dengan dua tampilan.
     *
     * @return array<string>
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteBaseName().'.*',
            JadwalRuang::getRouteName(),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('status', BookingStatus::Diajukan)
            ->where('end_at', '>=', now())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return RoomBookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomBookingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomBookings::route('/'),
            'create' => CreateRoomBooking::route('/create'),
            'view' => ViewRoomBooking::route('/{record}'),
            'edit' => EditRoomBooking::route('/{record}/edit'),
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