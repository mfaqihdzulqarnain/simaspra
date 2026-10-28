<?php

namespace App\Filament\Resources\Consumables\Schemas;

use App\Enums\ConsumableType;
use App\Models\Consumable;
use App\Models\Laboratory;
use App\Models\Room;
use App\Models\Unit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ConsumableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Barang')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Barang')
                        ->helperText('Contoh: KTS-A4')
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                    Select::make('type')
                        ->label('Jenis Barang')
                        ->options(ConsumableType::class)
                        ->default(ConsumableType::HabisPakai)
                        ->required()
                        ->live()
                        ->native(false)
                        ->helperText('Habis Pakai berkurang saat dipakai. Alat Massal dipinjam lalu kembali.'),

                    TextInput::make('name')
                        ->label('Nama Barang')
                        ->required()
                        ->maxLength(150),

                    Select::make('asset_category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    TextInput::make('unit_of_measure')
                        ->label('Satuan')
                        ->helperText('Contoh: rim, box, pcs, liter')
                        ->required()
                        ->maxLength(20)
                        ->default('pcs'),

                    Select::make('storage_room_id')
                        ->label('Ruang Penyimpanan')
                        ->options(fn () => Room::query()
                            ->with('building')
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn (Room $r) => [$r->id => $r->full_name]))
                        ->searchable()
                        ->native(false),

                    Select::make('unit_id')
                        ->label('Unit / Prodi')
                        ->options(fn () => static::unitOptions())
                        ->default(fn () => Auth::user()?->unit_id)
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->helperText('Pemilik barang. Menentukan siapa yang bisa melihatnya.'),

                    Select::make('laboratory_id')
                        ->label('Laboratorium')
                        ->options(fn () => static::labOptions())
                        ->searchable()
                        ->native(false)
                        ->live()
                        // Unit mengikuti unit pengelola laboratorium
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state && ($unit = Laboratory::find($state)?->unit_id)) {
                                $set('unit_id', $unit);
                            }
                        })
                        ->helperText('Isi bila barang ini dikelola laboratorium. Laboran lab tersebut yang mencatat stoknya; untuk alat massal, juga menentukan siapa yang bisa meminjam.'),

                    Toggle::make('is_active')
                        ->label('Barang Aktif')
                        ->default(true),
                ]),

            Section::make('Stok & Harga')
                ->columns(2)
                ->schema([
                    TextInput::make('on_loan')
                        ->label('Sedang Dipinjam')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Consumable $record, Get $get) => $record !== null
                            && $get('type') === ConsumableType::AlatMassal->value)
                        ->live(),

                    TextInput::make('min_stock')
                        ->label('Stok Minimum')
                        ->helperText('Sistem memberi peringatan bila stok mencapai angka ini.')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->default(0)
                        ->required(),

                    TextInput::make('unit_price')
                        ->label('Harga Satuan')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp')
                        ->default(0),

                    TextInput::make('current_stock')
                        ->label('Stok Saat Ini')
                        ->helperText('Diisi otomatis dari transaksi masuk dan keluar.')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Consumable $record) => $record !== null),

                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    /** Admin melihat semua unit; lainnya hanya unitnya sendiri, laboran juga unit lab-nya */
    protected static function unitOptions(): array
    {
        $user = Auth::user();
        $query = Unit::query()->orderBy('name');

        if ($user && ! $user->canSeeAllUnits()) {
            $ids = array_filter([
                $user->unit_id,
                ...Laboratory::query()->whereIn('id', $user->laboratoryIds())->pluck('unit_id')->all(),
            ]);
            $query->whereIn('id', $ids);
        }

        return $query->pluck('name', 'id')->all();
    }

    /** Admin melihat semua lab; laboran hanya lab yang diampu; lainnya lab di unitnya */
    protected static function labOptions(): array
    {
        $user = Auth::user();
        $query = Laboratory::query()->orderBy('name');

        if ($user && ! $user->canSeeAllUnits()) {
            $user->hasRole('laboran')
                ? $query->whereIn('id', $user->laboratoryIds())
                : $query->where('unit_id', $user->unit_id);
        }

        return $query->pluck('name', 'id')->all();
    }
}
