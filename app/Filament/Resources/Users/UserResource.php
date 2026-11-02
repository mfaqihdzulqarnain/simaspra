<?php

namespace App\Filament\Resources\Users;

// use App\Filament\Resources\Users\Pages\CreateUser;
// use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    /** Akun admin utama: disembunyikan dari daftar dan tidak dapat dihapus */
    public const AKUN_UTAMA = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?int $navigationSort = 80;

    protected static ?string $recordTitleAttribute = 'name';

    /** Badge akun menunggu aktivasi */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->whereDoesntHave('roles')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /** Akun admin utama hanya terlihat oleh dirinya sendiri */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                (int) Auth::id() !== self::AKUN_UTAMA,
                fn(Builder $query) => $query->whereKeyNot(self::AKUN_UTAMA),
            );
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && (int) $record->getKey() !== self::AKUN_UTAMA;
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record)
            && ((int) $record->getKey() !== self::AKUN_UTAMA || (int) Auth::id() === self::AKUN_UTAMA);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            //'create' => CreateUser::route('/create'),
            //'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}