<?php

namespace App\Filament\Resources\RoomBookings\Pages;

use App\Enums\AssetCondition;
use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Services\LoanService;
use App\Services\RentalFeeService;
use App\Services\RoomBookingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewRoomBooking extends ViewRecord
{
    protected static string $resource = RoomBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),

            Action::make('setujui')
                ->label('Setujui')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->modalHeading('Setujui Reservasi')
                ->modalDescription(fn (RoomBooking $record) => $record->room?->name.' · '.$record->schedule_label)
                ->fillForm(fn (RoomBooking $record) => [
                    'fee' => $record->isExternal()
                        ? RentalFeeService::calculate($record->room, $record->start_at, $record->end_at)['total']
                        : null,
                ])
                ->schema([
                    TextInput::make('fee')
                        ->label('Biaya Sewa')
                        ->prefix('Rp')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->helperText(fn () => RentalFeeService::summary(RentalFeeService::calculate(
                            $this->getRecord()->room,
                            $this->getRecord()->start_at,
                            $this->getRecord()->end_at,
                        )).' Ubah nominal bila ada kebijakan khusus; perubahan tercatat.')
                        ->visible(fn () => $this->getRecord()->isExternal()),

                    Textarea::make('note')->label('Catatan')->rows(2),
                ])
                ->visible(fn (RoomBooking $record) => RoomBookingService::canApprove($record))
                ->action(function (RoomBooking $record, array $data) {
                    try {
                        RoomBookingService::approve(
                            $record,
                            $data['note'] ?? null,
                            $record->isExternal() && isset($data['fee']) ? (float) $data['fee'] : null,
                        );
                    } catch (\DomainException $e) {
                        Notification::make()->danger()->title('Tidak dapat disetujui')->body($e->getMessage())->persistent()->send();

                        return;
                    }

                    Notification::make()->success()->title('Reservasi disetujui')->send();
                }),

            Action::make('tolak')
                ->label('Tolak')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->schema([
                    Textarea::make('reason')->label('Alasan Penolakan')->rows(3)->required(),
                ])
                ->visible(fn (RoomBooking $record) => RoomBookingService::canApprove($record))
                ->action(function (RoomBooking $record, array $data) {
                    RoomBookingService::reject($record, $data['reason']);

                    Notification::make()->danger()->title('Reservasi ditolak')->send();
                }),

            /* ------------------ Pembayaran sewa ------------------ */
            Action::make('unggah_bukti')
                ->label('Unggah Bukti Bayar')
                ->icon(Heroicon::OutlinedArrowUpOnSquare)
                ->color('warning')
                ->modalHeading('Unggah Bukti Bayar Sewa')
                ->modalDescription(fn (RoomBooking $record) => 'Tagihan '.RentalFeeService::rupiah($record->fee_amount)
                    .' untuk '.$record->booking_number.'.')
                ->schema([
                    FileUpload::make('bukti')
                        ->label('Bukti Transfer / Kuitansi')
                        ->disk('local')
                        ->directory('bukti-bayar-sementara')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(4096)
                        ->helperText('PDF atau foto, maksimal 4 MB.')
                        ->required(),

                    Textarea::make('note')->label('Keterangan')->rows(2)
                        ->placeholder('Contoh: transfer via BSI tanggal 10/10'),
                ])
                ->visible(fn (RoomBooking $record) => RoomBookingService::canSubmitPayment($record))
                ->action(function (RoomBooking $record, array $data) {
                    RoomBookingService::submitPaymentProof($record, $data['bukti'], $data['note'] ?? null);

                    Notification::make()->success()->title('Bukti bayar terkirim')
                        ->body('Menunggu verifikasi Bagian Sarana dan Prasarana.')->send();
                }),

            Action::make('tandai_lunas')
                ->label('Tandai Lunas')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pembayaran')
                ->modalDescription(fn (RoomBooking $record) => 'Tandai tagihan '.RentalFeeService::rupiah($record->fee_amount)
                    .' sebagai lunas. Pelengkap baru bisa diserahkan setelah lunas.')
                ->schema([
                    Textarea::make('note')->label('Catatan Verifikasi')->rows(2),
                ])
                ->visible(fn (RoomBooking $record) => RoomBookingService::canConfirmPayment($record))
                ->action(function (RoomBooking $record, array $data) {
                    RoomBookingService::confirmPayment($record, $data['note'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Pembayaran dikonfirmasi lunas')
                        ->body('Kuitansi '.$record->receipt_number.' diterbitkan.')
                        ->actions([
                            NotificationAction::make('cetak')
                                ->label('Cetak Kuitansi')
                                ->url(route('rental.receipt', $record->getKey()), shouldOpenInNewTab: true),
                        ])
                        ->send();
                }),

            ActionGroup::make([
                Action::make('cetak_tagihan')
                    ->label('Cetak Tagihan')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (RoomBooking $record) => route('rental.invoice', $record->getKey()), shouldOpenInNewTab: true)
                    ->visible(fn (RoomBooking $record) => RoomBookingService::canPrintInvoice($record)),

                Action::make('cetak_kuitansi')
                    ->label('Cetak Kuitansi')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->url(fn (RoomBooking $record) => route('rental.receipt', $record->getKey()), shouldOpenInNewTab: true)
                    ->visible(fn (RoomBooking $record) => RoomBookingService::canPrintReceipt($record)),
            ])
                ->label('Cetak')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->button()
                ->visible(fn (RoomBooking $record) => RoomBookingService::canPrintInvoice($record)),

            /* ------------------ Pelengkap: serahkan ------------------ */
            Action::make('serahkan_pelengkap')
                ->label('Serahkan Pelengkap')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Serahkan Pelengkap')
                ->modalDescription('Barang diserahkan kepada pemesan dan Berita Acara Peminjaman (BAPP) terbit.')
                ->schema([
                    Textarea::make('notes')->label('Catatan Serah Terima')->rows(2),
                ])
                ->visible(fn (RoomBooking $record) => $record->status === BookingStatus::Disetujui
                    && RoomBookingService::equipmentLoan($record)?->status === LoanStatus::Disetujui
                    && RoomBookingService::canHandleEquipment($record))
                ->action(function (RoomBooking $record, array $data) {
                    if ($alasan = RoomBookingService::handOutBlockedReason($record)) {
                        Notification::make()->danger()->title('Belum bisa diserahkan')->body($alasan)->send();

                        return;
                    }

                    $handover = LoanService::handOut(RoomBookingService::equipmentLoan($record), $data['notes'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Pelengkap diserahkan')
                        ->body('Berita acara '.$handover->number.' dibuat.')
                        ->actions([
                            NotificationAction::make('cetak')
                                ->label('Cetak BAPP')
                                ->url(route('bast.print', $handover), shouldOpenInNewTab: true),
                        ])
                        ->send();
                }),

            /* ------------------ Pelengkap: terima kembali ------------------ */
            Action::make('terima_pelengkap')
                ->label('Terima Pelengkap')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->modalHeading('Terima Pelengkap Kembali')
                ->modalDescription('Periksa kondisi tiap barang. Kerusakan atau kekurangan otomatis tercatat sebagai laporan kerusakan.')
                ->modalWidth('3xl')
                ->visible(fn (RoomBooking $record) => (bool) RoomBookingService::equipmentLoan($record)?->status->isOngoing()
                    && RoomBookingService::canHandleEquipment($record))
                ->fillForm(fn (RoomBooking $record) => [
                    'rows' => RoomBookingService::equipmentLoan($record)
                        ->items()->with('asset')->get()
                        ->map(fn ($item) => [
                            'item_id' => $item->getKey(),
                            'item_label' => ($item->asset?->code ?? '—').' — '.($item->asset?->name ?? '')
                                .($item->isCollective() ? ' — dipinjam '.$item->quantity.' unit' : ''),
                            'collective' => $item->isCollective(),
                            'quantity' => $item->quantity,
                            'condition' => ($item->condition_before ?? AssetCondition::Baik)->value,
                            'returned' => $item->quantity,
                            'damaged' => 0,
                        ])->all(),
                ])
                ->schema([
                    Repeater::make('rows')
                        ->hiddenLabel()
                        ->columns(2)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->itemLabel(fn (array $state) => $state['item_label'] ?? null)
                        ->schema([
                            Hidden::make('item_id'),
                            Hidden::make('item_label'),
                            Hidden::make('collective'),
                            Hidden::make('quantity'),

                            Select::make('condition')
                                ->label('Kondisi Saat Kembali')
                                ->options(AssetCondition::class)
                                ->required(fn (Get $get) => ! $get('collective'))
                                ->visible(fn (Get $get) => ! $get('collective'))
                                ->native(false),

                            TextInput::make('returned')
                                ->label('Kembali Baik')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(fn (Get $get) => (int) $get('quantity'))
                                ->suffix('unit')
                                ->required(fn (Get $get) => (bool) $get('collective'))
                                ->visible(fn (Get $get) => (bool) $get('collective')),

                            TextInput::make('damaged')
                                ->label('Rusak')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(fn (Get $get) => (int) $get('quantity'))
                                ->suffix('unit')
                                ->default(0)
                                ->helperText('Yang rusak dikurangkan dari yang kembali. Selebihnya dianggap hilang.')
                                ->visible(fn (Get $get) => (bool) $get('collective')),

                            Textarea::make('note')
                                ->label('Catatan Kerusakan')
                                ->rows(2)
                                ->placeholder('Isi bila ada kerusakan')
                                ->columnSpanFull(),
                        ]),

                    Textarea::make('notes')->label('Catatan Umum')->rows(2),
                ])
                ->action(function (RoomBooking $record, array $data) {
                    $kondisi = collect($data['rows'] ?? [])
                        ->mapWithKeys(fn ($row) => [
                            $row['item_id'] => [
                                'condition' => $row['condition'] ?? null,
                                'returned' => $row['returned'] ?? null,
                                'damaged' => $row['damaged'] ?? 0,
                                'note' => $row['note'] ?? null,
                            ],
                        ])
                        ->all();

                    $handover = LoanService::handBack(
                        RoomBookingService::equipmentLoan($record),
                        $kondisi,
                        $data['notes'] ?? null,
                    );

                    Notification::make()
                        ->success()
                        ->title('Pelengkap diterima kembali')
                        ->body('Berita acara '.$handover->number.' dibuat.')
                        ->actions([
                            NotificationAction::make('cetak')
                                ->label('Cetak BAPK')
                                ->url(route('bast.print', $handover), shouldOpenInNewTab: true),
                        ])
                        ->send();
                }),

            EditAction::make()
                ->label('Ubah')
                ->visible(fn (RoomBooking $record) => RoomBookingService::canEdit($record)),

            Action::make('batalkan')
                ->label('Batalkan')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Reservasi')
                ->modalDescription('Jadwal ruang akan dilepas dan bisa dipesan orang lain.')
                ->schema([
                    Textarea::make('reason')->label('Alasan Pembatalan')->rows(2),
                ])
                ->visible(fn (RoomBooking $record) => RoomBookingService::canCancel($record))
                ->action(function (RoomBooking $record, array $data) {
                    RoomBookingService::cancel($record, $data['reason'] ?? null);

                    Notification::make()->success()->title('Reservasi dibatalkan')->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reservasi')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('booking_number')->label('Nomor')->weight('bold'),

                    TextEntry::make('status_label')
                        ->label('Status')
                        ->badge()
                        ->color(fn (RoomBooking $record) => $record->status_color),

                    TextEntry::make('booking_type')->label('Jenis Kegiatan')->badge(),

                    TextEntry::make('title')->label('Nama Kegiatan')->columnSpan(2),

                    TextEntry::make('attendees')
                        ->label('Peserta')
                        ->formatStateUsing(fn ($state) => $state.' orang')
                        ->placeholder('—'),

                    TextEntry::make('description')
                        ->label('Keterangan')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Section::make('Ruang dan Waktu')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('room.name')
                        ->label('Ruang')
                        ->weight('medium')
                        ->hint(fn (RoomBooking $record) => $record->room?->building?->name),

                    TextEntry::make('schedule_label')->label('Jadwal')->columnSpan(2),

                    TextEntry::make('room.capacity')
                        ->label('Kapasitas Ruang')
                        ->formatStateUsing(fn ($state) => $state.' orang')
                        ->placeholder('—'),

                    TextEntry::make('room.picUser.name')->label('Penanggung Jawab Ruang')->placeholder('—'),

                    TextEntry::make('ruang_terpakai')
                        ->label('Ruang Terpakai')
                        ->state(function (RoomBooking $record) {
                            [$mulai, $selesai] = RoomBookingService::occupancy($record->room, $record->start_at, $record->end_at);

                            return $mulai->format('d/m/Y H.i').' – '.$selesai->format('d/m/Y H.i');
                        })
                        ->helperText(fn (RoomBooking $record) => 'Persiapan '
                            .Room::formatDuration($record->room?->setupMinutes() ?? 0)
                            .', beres-beres '.Room::formatDuration($record->room?->cleanupMinutes() ?? 0).'.'),
                ]),

            Section::make('Biaya Sewa')
                ->columnSpanFull()
                ->icon('heroicon-o-banknotes')
                ->columns(3)
                ->visible(fn (RoomBooking $record) => $record->isExternal())
                ->schema([
                    TextEntry::make('fee_amount')
                        ->label(fn (RoomBooking $record) => $record->status === BookingStatus::Diajukan ? 'Perkiraan Biaya' : 'Biaya Sewa')
                        ->formatStateUsing(fn ($state) => RentalFeeService::rupiah($state))
                        ->weight('bold')
                        ->placeholder('—'),

                    TextEntry::make('payment_status')
                        ->label('Status Pembayaran')
                        ->badge()
                        ->placeholder(fn (RoomBooking $record) => $record->status === BookingStatus::Diajukan
                            ? 'Ditagih setelah disetujui'
                            : 'Tanpa biaya'),

                    TextEntry::make('rentalDecree.number')
                        ->label('Dasar Tarif')
                        ->placeholder('—')
                        ->url(fn (RoomBooking $record) => $record->rentalDecree?->documentUrl(), shouldOpenInNewTab: true)
                        ->color('primary')
                        ->helperText(fn (RoomBooking $record) => $record->rentalDecree?->title),

                    RepeatableEntry::make('fee_breakdown.hari')
                        ->label('Rincian')
                        ->columns(4)
                        ->columnSpanFull()
                        ->visible(fn (RoomBooking $record) => ! empty($record->fee_breakdown['hari'] ?? []))
                        ->schema([
                            TextEntry::make('tanggal')->label('Tanggal'),
                            TextEntry::make('jam')->label('Jam Acara'),
                            TextEntry::make('tarif')->label('Tarif'),
                            TextEntry::make('harga')
                                ->label('Harga')
                                ->formatStateUsing(fn ($state) => RentalFeeService::rupiah($state)),
                        ]),

                    TextEntry::make('catatan_biaya')
                        ->label('Catatan')
                        ->columnSpanFull()
                        ->state(function (RoomBooking $record) {
                            $b = $record->fee_breakdown ?? [];

                            return collect([
                                $b['pesan'] ?? null,
                                isset($b['penyesuaian'])
                                    ? 'Disesuaikan oleh '.($b['penyesuaian']['oleh'] ?? 'penyetuju').' dari '
                                        .RentalFeeService::rupiah($b['penyesuaian']['dari']).' menjadi '
                                        .RentalFeeService::rupiah($b['penyesuaian']['menjadi']).'.'
                                    : null,
                            ])->filter()->implode(' ') ?: null;
                        })
                        ->visible(fn (RoomBooking $record) => filled($record->fee_breakdown['pesan'] ?? null)
                            || isset($record->fee_breakdown['penyesuaian'])),

                    TextEntry::make('bukti_bayar')
                        ->label('Bukti Bayar')
                        ->columnSpanFull()
                        ->html()
                        ->state(fn (RoomBooking $record) => $record->getMedia('bukti_bayar')
                            ->map(fn ($m) => '<a href="'.e($m->getUrl()).'" target="_blank" class="text-primary-600 underline">'
                                .e($m->file_name).'</a> <span style="opacity:.6">('.$m->created_at->format('d/m/Y H.i').')</span>')
                            ->implode('<br>') ?: null)
                        ->placeholder('Belum ada')
                        ->visible(fn (RoomBooking $record) => $record->payment_status !== null),

                    TextEntry::make('payment_note')->label('Keterangan Pembayaran')->placeholder('—')
                        ->visible(fn (RoomBooking $record) => filled($record->payment_note)),

                    TextEntry::make('receipt_number')
                        ->label('Nomor Kuitansi')
                        ->url(fn (RoomBooking $record) => route('rental.receipt', $record->getKey()), shouldOpenInNewTab: true)
                        ->color('primary')
                        ->visible(fn (RoomBooking $record) => filled($record->receipt_number)),

                    TextEntry::make('paid_at')
                        ->label('Dikonfirmasi')
                        ->dateTime('d/m/Y H.i')
                        ->helperText(fn (RoomBooking $record) => $record->paymentVerifier?->name)
                        ->visible(fn (RoomBooking $record) => $record->isPaid()),
                ]),

            Section::make('Pelengkap')
                ->columnSpanFull()
                ->visible(fn (RoomBooking $record) => $record->loan_id !== null)
                ->headerActions([
                    Action::make('buka_peminjaman')
                        ->label(fn (RoomBooking $record) => 'Peminjaman '.$record->loan?->loan_number)
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->color('gray')
                        ->size('sm')
                        ->url(fn (RoomBooking $record) => $record->loan
                            ? LoanResource::getUrl('view', ['record' => $record->loan])
                            : null)
                        ->visible(fn (RoomBooking $record) => $record->loan && Auth::user()?->canSeeAllUnits()),
                ])
                ->schema([
                    TextEntry::make('loan.status')
                        ->label('Status Pelengkap')
                        ->badge(),

                    TextEntry::make('loan_jadwal')
                        ->label('Dipinjam Untuk')
                        ->state(fn (RoomBooking $record) => $record->loan
                            ? $record->loan->start_at->format('d/m/Y H.i').' – '.$record->loan->end_at->format('d/m/Y H.i')
                            : null)
                        ->helperText('Sama dengan waktu ruang terpakai, termasuk persiapan dan beres-beres.'),

                    RepeatableEntry::make('loan.items')
                        ->label('Barang')
                        ->columns(4)
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('asset.code')->label('Kode')->weight('medium'),
                            TextEntry::make('asset.name')->label('Nama Barang'),
                            TextEntry::make('quantity')
                                ->label('Jumlah')
                                ->formatStateUsing(fn ($state) => $state.' unit'),
                            TextEntry::make('hasil')
                                ->label('Hasil Kembali')
                                ->state(fn ($record) => $record->returned_at
                                    ? ($record->isCollective()
                                        ? $record->return_summary
                                        : $record->condition_after?->getLabel())
                                    : null)
                                ->placeholder('—'),
                        ]),

                    RepeatableEntry::make('loan.handovers')
                        ->label('Berita Acara')
                        ->columns(3)
                        ->columnSpanFull()
                        ->visible(fn (RoomBooking $record) => (bool) $record->loan?->handovers()->exists())
                        ->schema([
                            TextEntry::make('number')
                                ->label('Nomor')
                                ->weight('medium')
                                ->url(fn ($record) => route('bast.print', $record), shouldOpenInNewTab: true)
                                ->color('primary'),
                            TextEntry::make('type')->label('Jenis')->badge(),
                            TextEntry::make('handover_date')->label('Tanggal')->date('d/m/Y'),
                        ]),
                ])
                ->columns(2),

            Section::make('Pemesan')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('borrower_name')->label('Nama')->weight('medium'),
                    TextEntry::make('borrower_type')->label('Jenis')->badge(),
                    TextEntry::make('borrower_institution')->label('Instansi')->placeholder('—'),
                    TextEntry::make('borrower_phone')->label('WhatsApp')->placeholder('—'),
                    TextEntry::make('borrower_email')->label('Email')->placeholder('—'),
                    TextEntry::make('creator.name')->label('Dicatat oleh')->placeholder('—'),
                ]),

            Section::make('Persetujuan')
                ->columnSpanFull()
                ->visible(fn (RoomBooking $record) => $record->status !== BookingStatus::Diajukan
                    || $record->approvals()->exists())
                ->schema([
                    TextEntry::make('rejection_reason')
                        ->label('Alasan Penolakan')
                        ->visible(fn (RoomBooking $record) => $record->status === BookingStatus::Ditolak),

                    TextEntry::make('cancellation_reason')
                        ->label('Alasan Pembatalan')
                        ->placeholder('—')
                        ->visible(fn (RoomBooking $record) => $record->status === BookingStatus::Batal),

                    RepeatableEntry::make('approvals')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('action')->label('Keputusan')->badge(),
                            TextEntry::make('user.name')->label('Oleh')->placeholder('—'),
                            TextEntry::make('acted_at')->label('Waktu')->dateTime('d/m/Y H:i'),
                            TextEntry::make('note')->label('Catatan')->placeholder('—'),
                        ]),
                ]),
        ]);
    }
}
