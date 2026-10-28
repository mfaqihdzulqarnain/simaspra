<?php

namespace App\Filament\Resources\MaintenanceSchedules\Schemas;

use App\Enums\WorkOrderType;
use App\Models\Asset;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MaintenanceScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cakupan Jadwal')
                ->schema([
                    Radio::make('scope')
                        ->label('Berlaku Untuk')
                        ->options([
                            'asset' => 'Satu aset tertentu',
                            'category' => 'Semua aset dalam satu kategori',
                        ])
                        ->default(fn (?MaintenanceSchedule $record) => $record?->asset_id ? 'asset' : 'category')
                        ->live()
                        ->dehydrated(false)
                        ->required(),

                    Select::make('asset_id')
                        ->label('Aset')
                        ->options(fn () => Asset::query()
                            ->allUnits()
                            ->active()
                            ->get()
                            ->mapWithKeys(fn (Asset $a) => [$a->id => $a->code . ' — ' . $a->name]))
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get) => $get('scope') === 'asset')
                        ->required(fn (Get $get) => $get('scope') === 'asset'),

                    Select::make('asset_category_id')
                        ->label('Kategori Aset')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (Get $get) => $get('scope') === 'category')
                        ->required(fn (Get $get) => $get('scope') === 'category')
                        ->helperText('Semua aset aktif dalam kategori ini akan dijadwalkan.'),
                ]),

            Section::make('Detail Jadwal')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Pekerjaan')
                        ->helperText('Contoh: Servis AC rutin, Kalibrasi timbangan')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    Select::make('type')
                        ->label('Jenis')
                        ->options(WorkOrderType::class)
                        ->default(WorkOrderType::Preventif)
                        ->required()
                        ->native(false),

                    TextInput::make('interval_months')
                        ->label('Interval (bulan)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120)
                        ->default(3)
                        ->required(),

                    DatePicker::make('last_done_at')
                        ->label('Terakhir Dikerjakan')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('next_due_at')
                        ->label('Jatuh Tempo Berikutnya')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->helperText('Kosongkan untuk dihitung otomatis dari interval.'),

                    Select::make('assigned_to')
                        ->label('Teknisi Penanggung Jawab')
                        ->options(fn () => User::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['teknisi', 'kabag_sarpras']))
                            ->where('is_active', true)
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->native(false),

                    Toggle::make('is_active')
                        ->label('Jadwal Aktif')
                        ->default(true),

                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}