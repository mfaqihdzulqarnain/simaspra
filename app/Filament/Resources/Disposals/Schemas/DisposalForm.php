<?php

namespace App\Filament\Resources\Disposals\Schemas;

// use App\Enums\DisposalMethod;
use App\Enums\DisposalReason;
// use App\Enums\DisposalStatus;
use App\Models\Asset;
use App\Models\Disposal;
use App\Services\DisposalService;
use Filament\Forms\Components\CheckboxList;
// use Filament\Forms\Components\DatePicker;
// use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
// use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DisposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Usulan')
                ->columns(2)
                ->schema([
                    Select::make('unit_id')
                        ->label('Unit Pengusul')
                        ->relationship('unit', 'name')
                        ->default(fn () => Auth::user()?->unit_id)
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->disabled(fn () => ! Auth::user()?->canSeeAllUnits())
                        ->dehydrated(),

                    Select::make('reason')
                        ->label('Alasan Penghapusan')
                        ->options(DisposalReason::class)
                        ->required()
                        ->native(false),

                    Textarea::make('description')
                        ->label('Uraian Alasan')
                        ->helperText('Jelaskan kondisi barang dan mengapa perlu dihapuskan.')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                ]),

            Section::make('Aset yang Diusulkan')
                ->description('Aset yang sedang dipinjam atau sudah masuk usulan lain tidak ditampilkan.')
                ->schema([
                    CheckboxList::make('asset_ids')
                        ->hiddenLabel()
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get, ?Disposal $record) {
                            $unitId = $get('unit_id');

                            if (blank($unitId)) {
                                return [];
                            }

                            return DisposalService::eligibleAssets((int) $unitId, $record?->getKey())
                                ->mapWithKeys(fn (Asset $a) => [
                                    $a->id => $a->code . ' — ' . $a->name
                                        . ' · ' . $a->condition->getLabel()
                                        . ($a->room ? ' · ' . $a->room->name : ''),
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(1)
                        ->required()
                        ->default(fn (?Disposal $record) => $record?->items()->pluck('asset_id')->all() ?? [])
                        ->dehydrated()
                        ->helperText(fn (\Filament\Schemas\Components\Utilities\Get $get) => blank($get('unit_id'))
                            ? 'Pilih unit pengusul terlebih dahulu.'
                            : 'Nilai buku dihitung otomatis saat disimpan.'),
                ]),
        ]);
    }
}