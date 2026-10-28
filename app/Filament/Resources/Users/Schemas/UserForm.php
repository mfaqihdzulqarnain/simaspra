<?php

namespace App\Filament\Resources\Users\Schemas;

// use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(150),

                    TextInput::make('nip')
                        ->label('NIP / NIDN')
                        ->maxLength(30)
                        ->unique(ignoreRecord: true),

                    TextInput::make('nim')
                        ->label('NIM')
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->helperText('Diisi untuk mahasiswa.'),

                    TextInput::make('entry_year')
                        ->label('Angkatan')
                        ->numeric()
                        ->minValue(1990)
                        ->maxValue((int) now()->addYear()->format('Y')),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(150)
                        ->unique(ignoreRecord: true),

                    TextInput::make('phone')
                        ->label('Nomor HP')
                        ->tel()
                        ->maxLength(20),

                    TextInput::make('position')
                        ->label('Jabatan')
                        ->helperText('Contoh: Kepala Program Studi D3 Kebidanan')
                        ->maxLength(120)
                        ->columnSpanFull(),
                ]),

            Section::make('Penempatan & Hak Akses')
                ->columns(2)
                ->schema([
                    Select::make('unit_id')
                        ->label('Unit')
                        ->relationship('unit', 'name')
                        ->helperText('Menentukan data mana yang bisa dilihat pengguna ini.')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('roles')
                        ->label('Peran')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Menentukan menu dan data yang bisa diakses pengguna.'),

                    Toggle::make('is_active')
                        ->label('Akun Aktif')
                        ->default(true)
                        ->helperText('Nonaktifkan untuk memblokir akses tanpa menghapus data.')
                        ->columnSpanFull(),
                ]),

            Section::make('Kata Sandi')
                ->columns(2)
                ->schema([
                    TextInput::make('password')
                        ->label('Kata Sandi')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Kosongkan bila tidak ingin mengubah kata sandi.'
                            : 'Minimal 8 karakter.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
