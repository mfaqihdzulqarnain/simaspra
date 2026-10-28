<?php

namespace App\Filament\Resources\RoomBookings\Schemas;

use App\Enums\BorrowerType;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Services\RoomBookingService;
use App\Services\LoanService;
use App\Services\RentalFeeService;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class RoomBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ruang dan Waktu')
                ->columns(2)
                ->schema([
                    Select::make('room_id')
                        ->label('Ruang')
                        ->options(fn () => Room::query()
                            ->bookable()
                            ->with('building:id,name')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Room $r) => [
                                $r->id => $r->name
                                    .($r->building ? ' — '.$r->building->name : '')
                                    .($r->capacity ? ' ('.$r->capacity.' orang)' : ''),
                            ])
                            ->all())
                        ->required()
                        ->searchable()
                        ->native(false)
                        // Dibuka dari QR ruang atau tombol Jadwal: ?ruang=12
                        ->default(fn () => request()->integer('ruang') ?: null)
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            // Jenis yang tidak diizinkan ruang baru dikosongkan
                            $jenis = $get('booking_type');

                            if ($jenis && ! array_key_exists($jenis, RoomBookingService::typeOptions($state ? (int) $state : null))) {
                                $set('booking_type', null);
                            }
                        })
                        ->helperText(fn (Get $get, ?RoomBooking $record) => static::agendaText(
                            $get('room_id'),
                            $get('start_at'),
                            $record?->getKey(),
                        )),

                    Select::make('booking_type')
                        ->label('Jenis Kegiatan')
                        ->options(fn (Get $get) => RoomBookingService::typeOptions($get('room_id') ? (int) $get('room_id') : null))
                        ->required()
                        ->native(false),

                    DateTimePicker::make('start_at')
                        ->label('Mulai')
                        ->required()
                        ->native(false)
                        ->seconds(false)
                        ->minutesStep(15)
                        ->displayFormat('d/m/Y H:i')
                        ->default(fn () => now()->addHour()->startOfHour())
                        ->minDate(fn (?RoomBooking $record) => $record ? null : now()->subMinutes(30))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            // Geser jam selesai bila jadi lebih awal dari jam mulai
                            if (! $state) {
                                return;
                            }

                            $selesai = $get('end_at') ? Carbon::parse($get('end_at')) : null;

                            if (! $selesai || $selesai->lte(Carbon::parse($state))) {
                                $set('end_at', Carbon::parse($state)->addHours(2)->format('Y-m-d H:i:s'));
                            }
                        }),

                    DateTimePicker::make('end_at')
                        ->label('Selesai')
                        ->required()
                        ->native(false)
                        ->seconds(false)
                        ->minutesStep(15)
                        ->displayFormat('d/m/Y H:i')
                        ->default(fn () => now()->addHours(3)->startOfHour())
                        ->after('start_at')
                        ->live(onBlur: true),
                ]),

            Section::make('Kegiatan')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Nama Kegiatan')
                        ->placeholder('Contoh: Rapat Koordinasi Akreditasi Prodi')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('attendees')
                        ->label('Jumlah Peserta')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->suffix('orang')
                        ->helperText(fn (Get $get) => ($cap = Room::find($get('room_id'))?->capacity)
                            ? 'Kapasitas ruang: '.$cap.' orang.'
                            : null),

                    Textarea::make('description')
                        ->label('Keterangan')
                        ->rows(3)
                        ->placeholder('Susunan acara, kebutuhan khusus, dan sebagainya')
                        ->columnSpanFull(),
                ]),

            Section::make('Biaya Sewa')
                ->description('Berlaku untuk pihak luar kampus, sesuai SK biaya sewa yang berlaku. Biaya pasti ditetapkan saat reservasi disetujui.')
                ->icon('heroicon-o-banknotes')
                ->visible(fn (Get $get) => static::isExternal($get)
                    || (bool) Auth::user()?->hasRole('peminjam_eksternal'))
                ->schema([
                    Text::make(fn (Get $get) => RentalFeeService::summary(RentalFeeService::calculate(
                        $get('room_id') ? Room::find($get('room_id')) : null,
                        $get('start_at'),
                        $get('end_at'),
                    ))),
                ]),

            Section::make('Pelengkap')
                ->description('Kursi, meja, podium, sound system, proyektor, dan barang lain yang ikut dipinjam. Barang laboratorium tidak termasuk.')
                ->collapsible()
                ->schema([
                    Repeater::make('pelengkap')
                        ->hiddenLabel()
                        ->addActionLabel('Tambah Barang')
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->columns(12)
                        ->schema([
                            Select::make('group_key')
                                ->label('Barang')
                                ->columnSpan(8)
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->options(fn (Get $get, ?RoomBooking $record) => static::equipmentGroups($get, $record)
                                    ->mapWithKeys(fn (array $g) => [
                                        $g['key'] => $g['label'].'  ('.$g['available'].' tersedia)',
                                    ])
                                    ->all())
                                ->afterStateUpdated(fn (Set $set) => $set('quantity', 1))
                                ->helperText(fn (Get $get) => blank($get('../../start_at')) || blank($get('../../end_at'))
                                    ? 'Isi jadwal terlebih dahulu.'
                                    : null),

                            TextInput::make('quantity')
                                ->label('Jumlah')
                                ->columnSpan(4)
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->suffix('unit')
                                ->maxValue(fn (Get $get, ?RoomBooking $record) => filled($get('group_key'))
                                    ? (static::equipmentGroups($get, $record)->firstWhere('key', $get('group_key'))['available'] ?? null)
                                    : null),
                        ])
                        ->afterStateHydrated(function (Repeater $component, ?RoomBooking $record) {
                            $loan = $record ? RoomBookingService::equipmentLoan($record) : null;

                            if (! $loan) {
                                return;
                            }

                            // Kelompokkan kembali barang yang sudah tersimpan
                            $component->state($loan->items()
                                ->with('asset.room', 'asset.unit')
                                ->get()
                                ->filter(fn ($i) => $i->asset !== null)
                                ->groupBy(fn ($i) => LoanService::groupKey($i->asset))
                                ->map(fn ($items, $key) => [
                                    'group_key' => $key,
                                    'quantity' => (int) $items->sum('quantity'),
                                ])
                                ->values()
                                ->all());
                        })
                        ->dehydrated(),
                ]),

            Section::make('Pemesan')
                ->columns(2)
                ->visible(fn () => RoomBookingService::canRecordExternal())
                ->description('Isi bila memesankan untuk pihak luar kampus. Pemesanan internal otomatis memakai akun Anda.')
                ->schema([
                    Select::make('borrower_type')
                        ->label('Jenis Pemesan')
                        ->options(BorrowerType::class)
                        ->default(BorrowerType::Internal->value)
                        ->required()
                        ->native(false)
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make('borrower_name')
                        ->label('Nama Penanggung Jawab')
                        ->required(fn (Get $get) => static::isExternal($get))
                        ->visible(fn (Get $get) => static::isExternal($get))
                        ->maxLength(255),

                    TextInput::make('borrower_institution')
                        ->label('Instansi / Organisasi')
                        ->required(fn (Get $get) => static::isExternal($get))
                        ->visible(fn (Get $get) => static::isExternal($get))
                        ->maxLength(255),

                    TextInput::make('borrower_phone')
                        ->label('Nomor WhatsApp')
                        ->tel()
                        ->required(fn (Get $get) => static::isExternal($get))
                        ->visible(fn (Get $get) => static::isExternal($get))
                        ->maxLength(30),

                    TextInput::make('borrower_email')
                        ->label('Email')
                        ->email()
                        ->visible(fn (Get $get) => static::isExternal($get))
                        ->maxLength(255),
                ]),
        ]);
    }

    /** Kelompok barang pelengkap untuk jadwal dan ruang yang sedang diisi */
    protected static function equipmentGroups(Get $get, ?RoomBooking $record)
    {
        return RoomBookingService::equipmentGroups(
            $get('../../room_id') ? (int) $get('../../room_id') : null,
            $get('../../start_at'),
            $get('../../end_at'),
            $record?->loan_id,
            RoomBookingService::borrowerFor($record, static::borrowerTypeValue($get('../../borrower_type'))),
        );
    }

    protected static function borrowerTypeValue($nilai): ?string
    {
        if ($nilai instanceof BorrowerType) {
            return $nilai->value;
        }

        return filled($nilai) ? (string) $nilai : null;
    }

    protected static function isExternal(Get $get): bool
    {
        $nilai = $get('borrower_type');

        return ($nilai instanceof BorrowerType ? $nilai->value : $nilai) === BorrowerType::Eksternal->value;
    }

    /** Pemakaian ruang pada tanggal terpilih, termasuk hari persiapan dan beres-beres */
    protected static function agendaText($roomId, $start, ?int $exceptId): ?string
    {
        if (! $roomId || ! $start) {
            return null;
        }

        $room = Room::find($roomId);
        $hari = Carbon::parse($start);
        $awalHari = $hari->copy()->startOfDay();
        $akhirHari = $hari->copy()->endOfDay();
        $tanggal = $hari->locale('id')->translatedFormat('l, j F Y');

        $aturan = collect([
            $room?->setupMinutes() ? 'persiapan '.Room::formatDuration($room->setupMinutes()).' sebelum acara' : null,
            $room?->cleanupMinutes() ? 'beres-beres '.Room::formatDuration($room->cleanupMinutes()).' sesudah acara' : null,
        ])->filter()->implode(' dan ');

        $catatanAturan = $aturan ? ' Ruang ini memerlukan '.$aturan.'.' : '';

        $agenda = RoomBookingService::dayAgenda((int) $roomId, $hari, $exceptId);

        if ($agenda->isEmpty()) {
            return 'Ruang kosong pada '.$tanggal.'.'.$catatanAturan;
        }

        $isi = $agenda->map(function ($b) use ($awalHari, $akhirHari) {
            $menunggu = $b->status->value === 'diajukan' ? ', menunggu' : '';

            // Acaranya sendiri jatuh pada hari ini
            if ($b->start_at->lte($akhirHari) && $b->end_at->gte($awalHari)) {
                return $b->start_at->format('H.i').'–'.$b->end_at->format('H.i').$menunggu;
            }

            // Hari ini hanya terpakai untuk persiapan atau beres-beres
            return $b->start_at->gt($akhirHari)
                ? 'persiapan acara '.$b->start_at->format('d/m').$menunggu
                : 'beres-beres acara '.$b->end_at->format('d/m').$menunggu;
        })->implode('; ');

        return 'Terpakai pada '.$tanggal.': '.$isi.'.'.$catatanAturan;
    }
}