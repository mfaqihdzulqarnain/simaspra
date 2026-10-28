<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'unit:id,name',
                'roles:id,name',
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (User $record) => $record->position),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('nip')->label('NIP')->searchable()->placeholder('—')->toggleable(),

                TextColumn::make('nim')->label('NIM')->searchable()->placeholder('—')->toggleable(),

                TextColumn::make('unit.name')->label('Unit')->placeholder('—')->sortable(),

                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->separator(',')
                    ->placeholder('Belum ada peran')
                    ->color(fn (?string $state) => $state ? 'primary' : 'warning'),

                TextColumn::make('last_login_at')
                    ->label('Terakhir Masuk')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Belum pernah')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Peran')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Status Akun')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->defaultSort('name')
            ->recordActions([
                \Filament\Actions\Action::make('reset_sandi')
                ->label('Reset Sandi')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedKey)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Kembalikan Kata Sandi')
                ->modalDescription(fn (User $record) => 'Kata sandi '
                    . $record->name . ' akan dikembalikan menjadi '
                    . ($record->identity_number ?? 'nomor identitasnya')
                    . '. Beri tahu yang bersangkutan untuk segera menggantinya.')
                ->modalSubmitActionLabel('Reset Sekarang')
                ->visible(fn (User $record) => filled($record->identity_number)
                    && Auth::user()?->hasAnyRole(['super_admin', 'kabag_sarpras']))
                ->action(function (User $record) {
                    $berhasil = $record->resetPasswordToIdentity();

                    \Filament\Notifications\Notification::make()
                        ->status($berhasil ? 'success' : 'danger')
                        ->title($berhasil ? 'Kata sandi direset' : 'Gagal')
                        ->body($berhasil
                            ? 'Kini sama dengan ' . $record->identity_number
                            : 'Pengguna belum punya NIM atau NIP.')
                        ->send();
                }),
                EditAction::make()->label(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('nonaktifkan')
                        ->label('Nonaktifkan Terpilih')
                        ->icon('heroicon-m-lock-closed')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan Akun')
                        ->modalDescription('Akun tidak bisa masuk sistem, tapi seluruh riwayatnya tetap tersimpan.')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super_admin', 'kabag_sarpras']))
                        ->action(function (Collection $records) {
                            // Cegah menonaktifkan akun sendiri
                            $sasaran = $records->reject(fn (User $u) => $u->getKey() === Auth::id());

                            $sasaran->each->update(['is_active' => false]);

                            Notification::make()
                                ->success()
                                ->title($sasaran->count() . ' akun dinonaktifkan')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('aktifkan')
                        ->label('Aktifkan Terpilih')
                        ->icon('heroicon-m-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn () => Auth::user()?->hasAnyRole(['super_admin', 'kabag_sarpras']))
                        ->action(function (Collection $records) {
                            $records->each->update(['is_active' => true]);

                            Notification::make()
                                ->success()
                                ->title($records->count() . ' akun diaktifkan')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada pengguna')
            ->emptyStateDescription('Tambahkan pengguna dan tetapkan peran serta unitnya.');
    }
}