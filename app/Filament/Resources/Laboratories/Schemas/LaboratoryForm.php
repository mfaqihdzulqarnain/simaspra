<?php

namespace App\Filament\Resources\Laboratories\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LaboratoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Laboratorium')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Lab')
                        ->helperText('Contoh: LAB-KOM, LAB-BID, LAB-TLM')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                    TextInput::make('name')
                        ->label('Nama Laboratorium')
                        ->required()
                        ->maxLength(150),

                    Select::make('unit_id')
                        ->label('Unit Pengelola')
                        ->relationship('unit', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('head_user_id')
                        ->label('Kepala Laboratorium')
                        ->relationship('head', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Toggle::make('is_active')
                        ->label('Laboratorium Aktif')
                        ->default(true)
                        ->columnSpanFull(),
                ]),

            Section::make('Laboran Bertugas')
                ->description('Laboran hanya melihat alat dan peminjaman di lab yang ditugaskan kepadanya.')
                ->schema([
                    Select::make('staff')
                        ->label('Laboran')
                        ->relationship('staff', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->native(false)
                        ->options(fn () => User::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['laboran', 'staf_ruang']))
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->helperText('Hanya pengguna berperan laboran atau staf ruangan yang bisa dipilih.'),
                ]),

            Section::make('Aturan Peminjaman')
                ->columns(2)
                ->schema([
                    TextInput::make('student_max_hours')
                        ->label('Batas Pinjam Mahasiswa')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(2160)
                        ->default(8)
                        ->required()
                        ->suffix('jam')
                        ->helperText('8 jam untuk satu sesi praktikum, 24 untuk sehari penuh.'),

                    TextInput::make('lecturer_max_hours')
                        ->label('Batas Pinjam Dosen')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(2160)
                        ->default(72)
                        ->required()
                        ->suffix('jam'),

                    TimePicker::make('opens_at')
                        ->label('Jam Buka')
                        ->seconds(false)
                        ->native(false),

                    TimePicker::make('closes_at')
                        ->label('Jam Tutup')
                        ->seconds(false)
                        ->native(false)
                        ->after('opens_at'),

                    Toggle::make('requires_approval')
                        ->label('Peminjaman perlu persetujuan laboran')
                        ->default(true)
                        ->columnSpanFull(),

                    Textarea::make('rules')
                        ->label('Tata Tertib')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Ditampilkan kepada peminjam saat mengajukan.'),
                ]),
        ]);
    }
}
