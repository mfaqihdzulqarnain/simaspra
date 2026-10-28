<?php

namespace App\Filament\Resources\Loans\Schemas;

use App\Enums\BorrowerType;
use App\Enums\LoanPurpose;
use App\Models\Consumable;
use App\Models\Laboratory;
use App\Models\Loan;
use App\Models\User;
use App\Services\ConsumableLoanService;
use App\Services\LoanService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /* --------------------------- Peminjam --------------------------- */
            Section::make('Data Peminjam')
                ->columns(2)
                ->schema([
                    Select::make('borrower_type')
                        ->label('Jenis Peminjam')
                        ->options(BorrowerType::class)
                        ->default(BorrowerType::Internal)
                        ->required()
                        ->live()
                        ->native(false),

                    Select::make('user_id')
                        ->label('Peminjam')
                        ->relationship('user', 'name')
                        ->searchable(['name', 'nim', 'nip', 'email'])
                        ->getOptionLabelFromRecordUsing(fn (User $u) => $u->name
                            .($u->identity_number ? ' · '.$u->identity_number : ''))
                        ->default(fn () => Auth::id())
                        ->live()
                        ->native(false)
                        ->disabled(fn () => ! static::canActForOthers())
                        ->dehydrated()
                        ->visible(fn (Get $get) => static::isInternal($get('borrower_type')))
                        ->required(fn (Get $get) => static::isInternal($get('borrower_type')))
                        ->helperText('Laboran dapat mencatat atas nama mahasiswa atau dosen yang datang langsung.')
                        ->afterStateUpdated(function (?int $state, Set $set) {
                            $u = $state ? User::find($state) : null;

                            if (! $u) {
                                return;
                            }

                            $set('borrower_name', $u->name);
                            $set('borrower_identity', $u->identity_number);
                            $set('borrower_phone', $u->phone);
                            $set('borrower_email', $u->email);
                        }),

                    TextInput::make('borrower_name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(150)
                        ->default(fn () => Auth::user()?->name),

                    TextInput::make('borrower_identity')
                        ->label('NIM / NIP / NIDN')
                        ->maxLength(30)
                        ->default(fn () => Auth::user()?->identity_number),

                    TextInput::make('borrower_institution')
                        ->label('Instansi / Organisasi')
                        ->maxLength(150)
                        ->visible(fn (Get $get) => static::isExternal($get('borrower_type')))
                        ->required(fn (Get $get) => static::isExternal($get('borrower_type'))),

                    TextInput::make('borrower_phone')
                        ->label('Nomor HP / WhatsApp')
                        ->tel()
                        ->required()
                        ->maxLength(20)
                        ->default(fn () => Auth::user()?->phone)
                        ->helperText('Dipakai untuk pengingat keterlambatan.'),

                    TextInput::make('borrower_email')
                        ->label('Email')
                        ->email()
                        ->maxLength(150)
                        ->default(fn () => Auth::user()?->email),
                ]),

            /* ------------------------ Lab & keperluan ----------------------- */
            Section::make('Laboratorium & Keperluan')
                ->columns(2)
                ->schema([
                    Select::make('laboratory_id')
                        ->label('Laboratorium')
                        ->options(fn () => Laboratory::active()->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => static::defaultLaboratory())
                        ->live()
                        ->native(false)
                        ->helperText('Kosongkan untuk peminjaman aset umum di luar laboratorium.'),

                    Select::make('purpose_type')
                        ->label('Jenis Keperluan')
                        ->options(LoanPurpose::class)
                        ->required()
                        ->live()
                        ->native(false),

                    TextInput::make('course_name')
                        ->label('Mata Kuliah')
                        ->maxLength(150)
                        ->visible(fn (Get $get) => static::purpose($get('purpose_type'))?->requiresCourse())
                        ->required(fn (Get $get) => static::purpose($get('purpose_type'))?->requiresCourse()),

                    Select::make('supervisor_id')
                        ->label('Dosen Penanggung Jawab')
                        ->options(fn () => User::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['dosen', 'kaprodi']))
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get) => static::purpose($get('purpose_type'))?->requiresSupervisor())
                        ->required(fn (Get $get) => static::purpose($get('purpose_type'))?->requiresSupervisor()),

                    TextInput::make('use_location')
                        ->label('Lokasi Penggunaan')
                        ->maxLength(150),

                    TextInput::make('deposit_amount')
                        ->label('Jaminan')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->prefix('Rp')
                        ->visible(fn (Get $get) => static::isExternal($get('borrower_type'))),

                    Textarea::make('purpose')
                        ->label('Uraian Keperluan')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                ]),

            /* ----------------------------- Jadwal ---------------------------- */
            Section::make('Jadwal')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('start_at')
                        ->label('Mulai Pinjam')
                        ->required()
                        ->native(false)
                        ->seconds(false)
                        ->minutesStep(15)
                        ->displayFormat('d/m/Y H:i')
                        ->default(fn () => now()->startOfHour())
                        ->minDate(fn (?Loan $record) => $record ? null : now()->subMinutes(30))
                        ->live(),

                    DateTimePicker::make('end_at')
                        ->label('Batas Kembali')
                        ->required()
                        ->native(false)
                        ->seconds(false)
                        ->minutesStep(15)
                        ->displayFormat('d/m/Y H:i')
                        ->default(fn () => now()->startOfHour()->addHours(4))
                        ->after('start_at'),
                ])
                ->columnSpanFull(),

            /* ------------------------------ Keranjang ---------------------------- */
            Section::make('Keranjang Alat')
                ->description('Alat berstiker QR, termasuk aset kolektif seperti kursi lipat yang dipinjam per jumlah. Kosongkan bila hanya meminjam alat massal.')
                ->schema([
                    Repeater::make('cart')
                        ->hiddenLabel()
                        ->addActionLabel('Tambah Jenis Alat')
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->columns(12)
                        ->schema([
                            Select::make('group_key')
                                ->label('Jenis Alat')
                                ->columnSpan(8)
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->options(function (Get $get, ?Loan $record) {
                                    $mulai = $get('../../start_at');
                                    $selesai = $get('../../end_at');

                                    if (blank($mulai) || blank($selesai)) {
                                        return [];
                                    }

                                    return LoanService::availableGroups(
                                        $mulai,
                                        $selesai,
                                        $record?->getKey(),
                                        static::labIdFrom($get, '../../'),
                                        static::borrowerFrom($get, '../../'),
                                    )
                                        ->mapWithKeys(fn (array $g) => [
                                            $g['key'] => $g['label'].'  ('.$g['available'].' tersedia)',
                                        ])
                                        ->all();
                                })
                                ->afterStateUpdated(fn (Set $set) => $set('quantity', 1))
                                ->helperText(fn (Get $get) => blank($get('../../start_at')) || blank($get('../../end_at'))
                                    ? 'Isi jadwal terlebih dahulu.'
                                    : null),

                            TextInput::make('quantity')
                                ->label('Jumlah')
                                ->columnSpan(4)
                                ->numeric()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->suffix('unit')
                                ->maxValue(function (Get $get, ?Loan $record) {
                                    $key = $get('group_key');

                                    if (blank($key)) {
                                        return null;
                                    }

                                    $mulai = $get('../../start_at');
                                    $selesai = $get('../../end_at');

                                    if (blank($mulai) || blank($selesai)) {
                                        return null;
                                    }

                                    return LoanService::availableGroups(
                                        $mulai,
                                        $selesai,
                                        $record?->getKey(),
                                        static::labIdFrom($get, '../../'),
                                        static::borrowerFrom($get, '../../'),
                                    )
                                        ->firstWhere('key', $key)['available'] ?? null;
                                }),
                        ])
                        ->afterStateHydrated(function (Repeater $component, ?Loan $record) {
                            if (! $record) {
                                return;
                            }

                            // Kelompokkan kembali item yang sudah tersimpan
                            $rows = $record->items()
                                ->with('asset.room', 'asset.unit')
                                ->get()
                                ->filter(fn ($i) => $i->asset !== null)
                                ->groupBy(fn ($i) => LoanService::groupKey($i->asset))
                                ->map(fn ($items, $key) => [
                                    'group_key' => $key,
                                    'quantity' => (int) $items->sum('quantity'),
                                ])
                                ->values()
                                ->all();

                            $component->state($rows);
                        })
                        ->dehydrated(),
                ]),

            /* ------------------------------ Alat Massal ---------------------------- */
            Section::make('Alat Massal')
                ->description('Gelas ukur, tabung reaksi, dan sejenisnya. Tidak berkode QR, dihitung per jumlah.')
                ->collapsed(false)
                ->schema([
                    Repeater::make('consumable_cart')
                        ->hiddenLabel()
                        ->addActionLabel('Tambah Alat Massal')
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->columns(12)
                        ->schema([
                            Select::make('consumable_id')
                                ->label('Alat')
                                ->columnSpan(8)
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->options(fn (Get $get, ?Loan $record) => ConsumableLoanService::available(
                                    static::labIdFrom($get, '../../'),
                                    $record?->getKey(),
                                )
                                    ->mapWithKeys(fn (Consumable $c) => [
                                        $c->id => $c->name.'  ('.$c->available_for
                                            .' '.$c->unit_of_measure.' tersedia)',
                                    ])
                                    ->all()),

                            TextInput::make('quantity')
                                ->label('Jumlah')
                                ->columnSpan(4)
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->maxValue(function (Get $get, ?Loan $record) {
                                    $id = $get('consumable_id');

                                    return $id
                                        ? ConsumableLoanService::availableQuantity(Consumable::find($id), $record?->getKey())
                                        : null;
                                }),
                        ])
                        ->afterStateHydrated(function (Repeater $component, ?Loan $record) {
                            if (! $record) {
                                return;
                            }

                            $component->state(
                                $record->consumableItems()
                                    ->get()
                                    ->map(fn ($i) => [
                                        'consumable_id' => $i->consumable_id,
                                        'quantity' => $i->quantity,
                                    ])
                                    ->all()
                            );
                        })
                        ->dehydrated(),
                ]),
        ]);
    }

    /* ------------------------------ Pembantu ------------------------------ */

    protected static function isInternal(mixed $v): bool
    {
        return ($v instanceof BorrowerType ? $v->value : $v) === BorrowerType::Internal->value;
    }

    protected static function isExternal(mixed $v): bool
    {
        return ($v instanceof BorrowerType ? $v->value : $v) === BorrowerType::Eksternal->value;
    }

    protected static function purpose(mixed $v): ?LoanPurpose
    {
        if ($v instanceof LoanPurpose) {
            return $v;
        }

        return filled($v) ? LoanPurpose::tryFrom((string) $v) : null;
    }

    protected static function labId(Get $get): ?int
    {
        return filled($get('laboratory_id')) ? (int) $get('laboratory_id') : null;
    }

    protected static function borrower(Get $get): ?User
    {
        return filled($get('user_id')) ? User::find($get('user_id')) : null;
    }

    /** Ambil nilai dari tingkat induk repeater */
    protected static function labIdFrom(Get $get, string $prefix = ''): ?int
    {
        $v = $get($prefix.'laboratory_id');

        return filled($v) ? (int) $v : null;
    }

    protected static function borrowerFrom(Get $get, string $prefix = ''): ?User
    {
        $v = $get($prefix.'user_id');

        return filled($v) ? User::find($v) : null;
    }

    /** Yang boleh mencatat pinjaman atas nama orang lain */
    protected static function canActForOthers(): bool
    {
        $user = Auth::user();

        return $user && ($user->canSeeAllUnits() || $user->hasRole('laboran'));
    }

    /** Laboran yang hanya memegang satu lab langsung terisi lab-nya */
    protected static function defaultLaboratory(): ?int
    {
        $user = Auth::user();

        if (! $user?->hasRole('laboran')) {
            return null;
        }

        $ids = $user->laboratoryIds();

        return count($ids) === 1 ? $ids[0] : null;
    }
}
