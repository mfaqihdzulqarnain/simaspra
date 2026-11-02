<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with([
                'unit:id,name',
                'roles:id,name',
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn(User $record) => $record->position),

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
                    ->color(fn(?string $state) => $state ? 'primary' : 'warning'),

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
                Action::make('reset_sandi')
                    ->label('Reset Sandi')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn(User $record) => 'Reset Sandi: ' . $record->name)
                    ->modalDescription(fn(User $record) => filled($record->nim) || filled($record->nip)
                        ? 'Kata sandi dikembalikan ke NIM/NIP. Minta pengguna segera menggantinya.'
                        : 'Akun ini tidak punya NIM maupun NIP, jadi isi kata sandi baru secara manual.')
                    ->modalSubmitActionLabel('Reset')
                    // Isian hanya muncul bila tidak ada NIM/NIP yang bisa dipakai
                    ->schema(fn(User $record) => filled($record->nim) || filled($record->nip) ? [] : [
                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->default(fn() => Str::password(12, symbols: false))
                            ->helperText('Minimal 8 karakter. Catat sebelum menyimpan.'),
                    ])
                    // Reset akun sendiri lewat menu Profil, bukan dari sini
                    ->visible(fn(User $record) => $record->getKey() !== Auth::id()
                        && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                    ->action(function (User $record, array $data) {
                        $sandi = $record->nim ?: ($record->nip ?: ($data['password'] ?? null));

                        if (blank($sandi)) {
                            Notification::make()->danger()->title('Kata sandi baru wajib diisi')->send();

                            return;
                        }

                        $record->forceFill(['password' => Hash::make($sandi)])->save();

                        Notification::make()
                            ->success()
                            ->title('Kata sandi direset')
                            ->body(filled($record->nim) || filled($record->nip)
                                ? 'Kembali ke ' . (filled($record->nim) ? 'NIM' : 'NIP') . '. Minta pengguna segera menggantinya.'
                                : 'Kata sandi baru: ' . $sandi . ' — catat sekarang, pesan ini tidak tersimpan.')
                            ->persistent()
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
                        ->visible(fn() => Auth::user()?->hasAnyRole(['super_admin', 'kabag_sarpras']))
                        ->action(function (Collection $records) {
                            // Cegah menonaktifkan akun sendiri
                            $sasaran = $records->reject(fn(User $u) => $u->getKey() === Auth::id());

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
                        ->visible(fn() => Auth::user()?->hasAnyRole(['super_admin', 'kabag_sarpras']))
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
