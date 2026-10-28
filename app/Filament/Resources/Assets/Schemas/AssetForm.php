<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AcquisitionSource;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LoanableBy;
use App\Models\Asset;
use App\Models\Laboratory;
use App\Models\Room;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Aset')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Aset')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    TextInput::make('quantity')
                        ->label('Jumlah Unit')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(1)
                        ->required()
                        ->suffix('unit')
                        ->live(onBlur: true)
                    // Nilai per unit dipertahankan, total mengikuti jumlah
                        ->afterStateUpdated(function (Get $get, Set $set, $state, $old) {
                            $jumlahLama = max(1, (int) $old);
                            $jumlahBaru = max(1, (int) $state);

                            $perUnit = filled($get('nilai_per_unit'))
                                ? (float) $get('nilai_per_unit')
                                : (float) $get('acquisition_value') / $jumlahLama;

                            $set('nilai_per_unit', round($perUnit, 2));
                            $set('acquisition_value', round($perUnit * $jumlahBaru, 2));
                        })
                        ->helperText('Isi lebih dari 1 untuk aset kolektif, misalnya kursi lipat dengan satu kode QR.'),

                    Select::make('asset_category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('unit_id')
                        ->label('Unit Pemilik')
                        ->relationship('unit', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('laboratory_filter')
                        ->label('Laboratorium')
                        ->options(fn () => Laboratory::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->placeholder('Semua ruangan')
                        ->native(false)
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Select $component, ?Asset $record) {
                            // Isi otomatis dari ruang aset saat membuka halaman edit
                            $component->state($record?->room?->laboratory_id);
                        })
                        ->afterStateUpdated(fn (Set $set) => $set('room_id', null))
                        ->helperText('Pilih untuk menyaring daftar ruangan di sebelah.'),

                    Select::make('room_id')
                        ->label('Ruangan')
                        ->options(function (Get $get) {
                            return Room::query()
                                ->with(['building', 'laboratory'])
                                ->where('is_active', true)
                                ->when($get('laboratory_filter'),
                                    fn ($q, $lab) => $q->where('laboratory_id', $lab))
                                ->get()
                                ->mapWithKeys(fn (Room $r) => [
                                    $r->id => $r->full_name
                                        .($r->laboratory ? ' · '.$r->laboratory->name : ''),
                                ]);
                        })
                        ->searchable()
                        ->native(false)
                        ->helperText('Laboratorium alat ditentukan oleh ruangannya.'),

                    TextInput::make('brand')->label('Merek')->maxLength(100),
                    TextInput::make('model')->label('Model')->maxLength(100),
                    TextInput::make('serial_number')->label('Nomor Seri')->maxLength(100),
                ]),

            Section::make('Perolehan')
                ->columns(3)
                ->schema([
                    TextInput::make('year_acquired')
                        ->label('Tahun Perolehan')
                        ->numeric()
                        ->minValue(1990)
                        ->maxValue((int) now()->addYear()->format('Y'))
                        ->default((int) now()->format('Y'))
                        ->required(),

                    DatePicker::make('acquisition_date')
                        ->label('Tanggal Perolehan')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Select::make('acquisition_source')
                        ->label('Sumber')
                        ->options(AcquisitionSource::class)
                        ->default(AcquisitionSource::Pembelian)
                        ->required()
                        ->native(false),

                        // Hanya tampil untuk aset kolektif; tidak disimpan, dipakai menghitung total
                    TextInput::make('nilai_per_unit')
                        ->label('Nilai per Unit')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp')
                        ->dehydrated(false)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get) => (int) $get('quantity') > 1)
                        ->afterStateHydrated(function (Set $set, ?Asset $record) {
                            if ($record) {
                                $set('nilai_per_unit', round($record->unitValue(), 2));
                            }
                        })
                        ->afterStateUpdated(fn (Get $get, Set $set, $state) => $set(
                            'acquisition_value',
                            round((float) $state * max(1, (int) $get('quantity')), 2),
                        )),

                    TextInput::make('acquisition_value')
                        ->label(fn (Get $get) => (int) $get('quantity') > 1 ? 'Nilai Perolehan (Total)' : 'Nilai Perolehan')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp')
                        ->default(0)
                        ->live(onBlur: true)
                        ->helperText(fn (Get $get) => (int) $get('quantity') > 1
                            ? 'Terhitung otomatis dari nilai per unit × jumlah. Bisa diubah langsung bila totalnya berbeda.'
                            : null)
                        // Total diubah langsung: nilai per unit menyesuaikan
                        ->afterStateUpdated(fn (Get $get, Set $set, $state) => $set(
                            'nilai_per_unit',
                            round((float) $state / max(1, (int) $get('quantity')), 2),
                        )),

                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->relationship('vendor', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('contract_id')
                        ->label('Kontrak')
                        ->relationship('contract', 'number')
                        ->searchable()
                        ->preload()
                        ->native(false),
                ]),

            Section::make('Kondisi & Status')
                ->columns(2)
                ->schema([
                    Select::make('condition')
                        ->label('Kondisi')
                        ->options(AssetCondition::class)
                        ->default(AssetCondition::Baik)
                        ->required()
                        ->native(false),

                    Select::make('status')
                        ->label('Status')
                        ->options(AssetStatus::class)
                        ->default(AssetStatus::Aktif)
                        ->required()
                        ->native(false)
                        ->disabled(fn (?Asset $record) => $record !== null)
                        ->dehydrated()
                        ->helperText('Status berubah otomatis lewat peminjaman, perbaikan, dan penghapusan.'),

                    DatePicker::make('warranty_until')
                        ->label('Garansi Sampai')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    DatePicker::make('calibration_due_at')
                        ->label('Kalibrasi Berikutnya')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Toggle::make('is_loanable')
                        ->label('Boleh Dipinjam')
                        ->helperText('Aktifkan bila aset ini dapat dipinjam.')
                        ->live()
                        ->columnSpanFull(),

                    Select::make('loanable_by')
                        ->label('Siapa yang Boleh Meminjam')
                        ->options(LoanableBy::class)
                        ->default(LoanableBy::Semua)
                        ->required()
                        ->native(false)
                        ->visible(fn (Get $get) => $get('is_loanable'))
                        ->helperText('Alat mahal atau berisiko sebaiknya dibatasi untuk dosen.'),
                ]),

            Section::make('Foto & Dokumen')
                ->columns(2)
                ->schema([
                    SpatieMediaLibraryFileUpload::make('foto')
                        ->label('Foto Aset')
                        ->helperText('Hanya file Gambar. Maksimal 2 MB.')
                        ->collection('foto')
                        ->image()
                        ->imageEditor()
                        ->maxSize(3072),

                    SpatieMediaLibraryFileUpload::make('dokumen')
                        ->label('Dokumen')
                        ->helperText('Hanya file PDF. Maksimal 4 MB.')
                        ->collection('dokumen')
                        ->multiple()
                        ->reorderable()
                        ->preserveFilenames()
                        ->maxSize(4096)
                        ->acceptedFileTypes(['application/pdf'])
                        ->openable()
                        ->downloadable(),
                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
