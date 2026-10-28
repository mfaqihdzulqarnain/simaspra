<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

// use App\Enums\AssetCondition;
use App\Enums\PriorityLevel;
// use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\Asset;
use App\Models\User;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
// use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
// use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pekerjaan')
                ->columns(2)
                ->schema([
                    Select::make('asset_id')
                        ->label('Aset')
                        ->options(fn () => Asset::query()
                            ->allUnits()
                            ->active()
                            ->get()
                            ->mapWithKeys(fn (Asset $a) => [$a->id => $a->code . ' — ' . $a->name]))
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->columnSpanFull(),

                    Select::make('type')
                        ->label('Jenis')
                        ->options(WorkOrderType::class)
                        ->default(WorkOrderType::Korektif)
                        ->required()
                        ->native(false),

                    Select::make('priority')
                        ->label('Prioritas')
                        ->options(PriorityLevel::class)
                        ->default(PriorityLevel::Sedang)
                        ->required()
                        ->native(false),

                    Select::make('assigned_to')
                        ->label('Teknisi')
                        ->options(fn () => User::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['teknisi', 'kabag_sarpras']))
                            ->where('is_active', true)
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->native(false),

                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(fn () => Vendor::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->native(false),

                    DatePicker::make('scheduled_at')
                        ->label('Rencana Pengerjaan')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Textarea::make('description')
                        ->label('Uraian Pekerjaan')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                ])->columnSpanFull(),
        ]);
    }
}