<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Enums\BookingType;
use App\Models\Building;
use App\Models\Room;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Ruangan')
                ->columns(2)
                ->schema([
                    Select::make('building_id')
                        ->label('Gedung')
                        ->relationship('building', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live(),

                    TextInput::make('floor')
                        ->label('Lantai')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required()
                        ->maxValue(fn ($get) => Building::find($get('building_id'))?->floor_count ?? 50),

                    TextInput::make('code')
                        ->label('Kode Ruang')
                        ->helperText('Contoh: GDA-201')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true),

                    TextInput::make('name')
                        ->label('Nama Ruang')
                        ->required()
                        ->maxLength(150),
                ]),

            Section::make('Penanggung Jawab & Kapasitas')
                ->columns(2)
                ->schema([
                    Select::make('unit_id')
                        ->label('Unit Pengelola')
                        ->relationship('unit', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('laboratory_id')
                        ->label('Laboratorium')
                        ->relationship('laboratory', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Isi bila ruangan ini bagian dari laboratorium. Alat di dalamnya otomatis terkelompok ke lab tersebut.'),

                    Select::make('pic_user_id')
                        ->label('Penanggung Jawab')
                        ->relationship('picUser', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    TextInput::make('area_m2')
                        ->label('Luas (m²)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01),

                    TextInput::make('capacity')
                        ->label('Kapasitas (orang)')
                        ->numeric()
                        ->minValue(0),

                    Toggle::make('is_active')
                        ->label('Ruangan Aktif')
                        ->default(true)
                        ->columnSpanFull(),
                ]),

            Section::make('Reservasi')
                ->description('Buka ruang ini untuk dipesan lewat menu Reservasi Ruang, misalnya aula, ruang rapat, atau kelas.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    Toggle::make('is_bookable')
                        ->label('Bisa Dipesan')
                        ->helperText('Penanggung jawab ruang menyetujui reservasi internal. Reservasi pihak luar disetujui Kabag Sarpras.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    CheckboxList::make('booking_types')
                        ->label('Jenis Kegiatan yang Diizinkan')
                        ->options(BookingType::class)
                        ->descriptions(collect(BookingType::cases())
                            ->mapWithKeys(fn (BookingType $t) => [$t->value => $t->getDescription()])
                            ->all())
                        ->helperText('Kosongkan untuk mengizinkan semua jenis.')
                        ->visible(fn (Get $get) => (bool) $get('is_bookable')),

                    ...static::durationFields(
                        'booking_setup_minutes',
                        'Persiapan Sebelum Acara',
                        'Ruang dikunci sejak waktu ini sebelum acara, misalnya untuk dekorasi dan penataan. Aula umumnya 1–2 hari, ruang rapat bisa tanpa jeda.',
                        1,
                        'hari',
                        3 * 1440,
                    ),

                    ...static::durationFields(
                        'booking_cleanup_minutes',
                        'Beres-beres Sesudah Acara',
                        'Ruang belum bisa dipakai lagi selama waktu ini setelah acara selesai. Disarankan minimal 4 jam untuk aula.',
                        4,
                        'jam',
                        3 * 1440,
                    ),
                ]),
        ]);
    }

    /**
     * Isian durasi dengan pilihan satuan (menit, jam, hari).
     * Yang tersimpan di database tetap dalam menit.
     */
    protected static function durationFields(
        string $kolom,
        string $label,
        string $bantuan,
        int $bawaanJumlah,
        string $bawaanSatuan,
        int $maksMenit,
    ): array {
        $jumlah = $kolom.'_jumlah';
        $satuan = $kolom.'_satuan';
        $faktor = ['menit' => 1, 'jam' => 60, 'hari' => 1440];

        $keMenit = fn (Get $get) => max(0, (int) $get($jumlah)) * ($faktor[$get($satuan)] ?? 1);

        return [
            Grid::make(2)
                ->columnSpanFull()
                ->visible(fn (Get $get) => (bool) $get('is_bookable'))
                ->schema([
                    TextInput::make($jumlah)
                        ->label($label)
                        ->helperText($bantuan)
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->default($bawaanJumlah)
                        ->live(onBlur: true)
                        ->dehydrated(false)
                        ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($keMenit, $get, $maksMenit) {
                            if ($keMenit($get) > $maksMenit) {
                                $fail('Paling lama '.Room::formatDuration($maksMenit).'.');
                            }
                        })
                        ->afterStateHydrated(function (Set $set, ?Room $record) use ($kolom, $jumlah, $satuan) {
                            if (! $record) {
                                return;
                            }

                            // Tampilkan dengan satuan terbesar yang pas
                            $menit = (int) $record->{$kolom};

                            [$nilai, $unit] = match (true) {
                                $menit > 0 && $menit % 1440 === 0 => [intdiv($menit, 1440), 'hari'],
                                $menit > 0 && $menit % 60 === 0 => [intdiv($menit, 60), 'jam'],
                                default => [$menit, 'menit'],
                            };

                            $set($jumlah, $nilai);
                            $set($satuan, $unit);
                        }),

                    Select::make($satuan)
                        ->label('Satuan')
                        ->options(['menit' => 'Menit', 'jam' => 'Jam', 'hari' => 'Hari'])
                        ->default($bawaanSatuan)
                        ->required()
                        ->native(false)
                        ->live()
                        ->dehydrated(false),
                ]),

            Hidden::make($kolom)
                ->dehydrateStateUsing(fn (Get $get) => $keMenit($get)),
        ];
    }
}
