<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\AssetCondition;
use App\Enums\LoanStatus;
use App\Filament\Actions\SendReminderAction;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Models\Loan;
use App\Services\LoanService;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendReminderAction::make('ingatkan_header'),
            /* -------------------- Setujui -------------------- */
            Action::make('setujui')
                ->label('Setujui')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->schema([
                    Textarea::make('note')->label('Catatan')->rows(2),
                ])
                // ->visible(fn (Loan $record) => $record->status === LoanStatus::Diajukan
                //     && ! $record->isBookingEquipment()
                //     && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin', 'laboran']))
                ->visible(fn (Loan $record) => LoanService::canDecide($record))
                ->action(function (Loan $record, array $data) {
                    LoanService::approve($record, $data['note'] ?? null);

                    Notification::make()->success()->title('Peminjaman disetujui')->send();
                }),

            Action::make('tolak')
                ->label('Tolak')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->schema([
                    Textarea::make('reason')->label('Alasan Penolakan')->rows(3)->required(),
                ])
                ->visible(fn (Loan $record) => $record->status === LoanStatus::Diajukan
                    && ! $record->isBookingEquipment()
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin', 'laboran']))
                ->action(function (Loan $record, array $data) {
                    LoanService::reject($record, $data['reason']);

                    Notification::make()->danger()->title('Peminjaman ditolak')->send();
                }),

            /* ------------------ Serahkan aset ------------------ */
            Action::make('serahkan')
                ->label('Serahkan Aset')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Serah Terima Keluar')
                ->modalDescription('Status aset berubah menjadi Sedang Dipinjam dan berita acara dibuat.')
                ->schema([
                    Textarea::make('notes')->label('Catatan Serah Terima')->rows(2),
                ])
                ->visible(fn (Loan $record) => $record->status === LoanStatus::Disetujui
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin', 'laboran']))
                ->action(function (Loan $record, array $data) {
                    $handover = LoanService::handOut($record, $data['notes'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Aset diserahkan')
                        ->body('Berita acara '.$handover->number.' dibuat.')
                        ->send();
                }),

            /* ------------------ Terima kembali ------------------ */
            Action::make('kembalikan')
                ->label('Terima Pengembalian')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->modalWidth('3xl')
                ->modalHeading('Pengembalian Aset')
                ->modalDescription('Periksa kondisi tiap aset. Kondisi yang memburuk otomatis membuat laporan kerusakan. Alat massal yang rusak atau hilang mengurangi stok.')
                ->modalSubmitActionLabel('Terima & Buat Berita Acara')
                ->visible(fn (Loan $record) => $record->isOngoing()
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin', 'laboran']))
                ->fillForm(fn (Loan $record) => [
                    'rows' => $record->items()->with('asset')->get()->map(fn ($item) => [
                        'item_id' => $item->getKey(),
                        'item_label' => ($item->asset?->code ?? '—').' — '.($item->asset?->name ?? '')
                            .($item->isCollective() ? ' — dipinjam '.$item->quantity.' unit' : ''),
                        'collective' => $item->isCollective(),
                        'quantity' => $item->quantity,
                        'condition' => ($item->condition_before ?? AssetCondition::Baik)->value,
                        'returned' => $item->quantity,
                        'damaged' => 0,
                    ])->all(),
                    'massal' => $record->consumableItems()->with('consumable')->get()->map(fn ($item) => [
                        'item_id' => $item->getKey(),
                        'item_label' => ($item->consumable?->name ?? '—').' — dipinjam '
                            .$item->quantity.' '.($item->consumable?->unit_of_measure ?? ''),
                        'quantity' => $item->quantity,
                        'returned' => $item->quantity,
                        'damaged' => 0,
                    ])->all(),
                ])
                ->schema([
                    Repeater::make('rows')
                        ->hiddenLabel()
                        ->visible(fn () => $this->getRecord()->items()->exists())
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

                            // Aset tunggal: pilih kondisi
                            Select::make('condition')
                                ->label('Kondisi Saat Kembali')
                                ->options(AssetCondition::class)
                                ->required(fn (Get $get) => ! $get('collective'))
                                ->visible(fn (Get $get) => ! $get('collective'))
                                ->native(false),

                            // Aset kolektif: hitung jumlah
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

                    Repeater::make('massal')
                        ->label('Alat Massal')
                        ->columns(3)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->visible(fn () => $this->getRecord()->consumableItems()->exists())
                        ->itemLabel(fn (array $state) => $state['item_label'] ?? null)
                        ->schema([
                            Hidden::make('item_id'),
                            Hidden::make('item_label'),
                            Hidden::make('quantity'),

                            TextInput::make('returned')
                                ->label('Kembali Baik')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(fn (Get $get) => (int) $get('quantity'))
                                ->required(),

                            TextInput::make('damaged')
                                ->label('Rusak')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(fn (Get $get) => (int) $get('quantity'))
                                ->default(0)
                                ->required()
                                ->helperText('Yang rusak dikurangkan dari yang kembali. Selebihnya dianggap hilang.'),

                            TextInput::make('note')
                                ->label('Catatan')
                                ->maxLength(255),
                        ]),

                    Textarea::make('notes')->label('Catatan Umum')->rows(2),
                ])
                ->action(function (Loan $record, array $data) {
                    $conditions = collect($data['rows'] ?? [])
                        ->mapWithKeys(fn ($row) => [
                            $row['item_id'] => [
                                'condition' => $row['condition'] ?? null,
                                'returned' => $row['returned'] ?? null,
                                'damaged' => $row['damaged'] ?? 0,
                                'note' => $row['note'] ?? null,
                            ],
                        ])
                        ->all();

                    $massal = collect($data['massal'] ?? [])
                        ->mapWithKeys(fn ($row) => [
                            $row['item_id'] => [
                                'returned' => $row['returned'] ?? null,
                                'damaged' => $row['damaged'] ?? 0,
                                'note' => $row['note'] ?? null,
                            ],
                        ])
                        ->all();

                    $handover = LoanService::handBack($record, $conditions, $data['notes'] ?? null, $massal);

                    Notification::make()
                        ->success()
                        ->title('Pengembalian dicatat')
                        ->body('Berita acara '.$handover->number.' dibuat.')
                        ->send();
                }),

            EditAction::make()->label('Ubah')
                ->visible(fn (Loan $record) => $record->status === LoanStatus::Diajukan),

            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Section::make('Data Peminjaman')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('loan_number')->label('Nomor')->weight('bold'),
                        TextEntry::make('reservasi')
                            ->label('Pelengkap Reservasi')
                            ->state(fn (Loan $record) => $record->roomBooking?->booking_number)
                            ->helperText('Persetujuan dan perubahan dilakukan dari halaman reservasi.')
                            ->url(fn (Loan $record) => $record->roomBooking
                                ? RoomBookingResource::getUrl('view', ['record' => $record->roomBooking])
                                : null)
                            ->color('primary')
                            ->visible(fn (Loan $record) => $record->isBookingEquipment()),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('borrower_name')->label('Peminjam'),
                        TextEntry::make('borrower_identity')->label('NIM / NIP')->placeholder('—'),
                        TextEntry::make('borrower_type')->label('Jenis')->badge(),
                        TextEntry::make('borrower_phone')
                            ->label('Kontak')
                            ->formatStateUsing(fn ($state) => Phone::display($state)),
                        TextEntry::make('laboratory.name')->label('Laboratorium')->placeholder('Umum'),
                        TextEntry::make('purpose_type')->label('Keperluan')->badge()->placeholder('—'),
                        TextEntry::make('course_name')->label('Mata Kuliah')->placeholder('—'),
                        TextEntry::make('supervisor.name')->label('Dosen Penanggung Jawab')->placeholder('—'),
                        TextEntry::make('start_at')->label('Mulai')->dateTime('d/m/Y H:i'),
                        TextEntry::make('end_at')
                            ->label('Batas Kembali')
                            ->dateTime('d/m/Y H:i')
                            ->color(fn (Loan $record) => $record->is_overdue ? 'danger' : null)
                            ->helperText(fn (Loan $record) => $record->late_label
                                ? 'Terlambat '.$record->late_label
                                : null),
                        TextEntry::make('returned_at')->label('Dikembalikan')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('use_location')->label('Lokasi Penggunaan')->placeholder('—'),
                        TextEntry::make('approver.name')->label('Disetujui Oleh')->placeholder('—'),
                        TextEntry::make('deposit_amount')
                            ->label('Jaminan')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                            ->visible(fn (Loan $record) => (float) $record->deposit_amount > 0),
                        TextEntry::make('purpose')->label('Uraian Keperluan')->columnSpanFull(),
                        TextEntry::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->columnSpanFull()
                            ->visible(fn (Loan $record) => $record->status === LoanStatus::Ditolak),
                    ]),

                Section::make('Berita Acara')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('bast')
                            ->hiddenLabel()
                            ->state(function (Loan $record) {
                                $handovers = $record->handovers()->get();

                                if ($handovers->isEmpty()) {
                                    return new HtmlString(
                                        '<p style="font-size:13px;color:#667085">Belum ada berita acara.</p>'
                                    );
                                }

                                $html = '<div style="width:100%">';

                                foreach ($handovers as $h) {
                                    $html .= '<div style="width:100%;margin-bottom:10px;padding:10px;'
                                        .'border:1px solid #E3E8EF;border-radius:8px">'
                                        .'<p style="margin:0;font-size:13px;font-weight:600">'
                                        .e($h->type->getLabel()).'</p>'
                                        .'<p style="margin:2px 0 6px;font-size:12px;color:#667085">'
                                        .e($h->number).' · '
                                        .$h->handover_date->translatedFormat('d M Y').'</p>'
                                        .'<a href="'.route('bast.print', $h).'" target="_blank" rel="noopener"'
                                        .' style="font-size:12px;color:#2E90FA;text-decoration:none">Cetak</a>'
                                        .'</div>';
                                }

                                return new HtmlString($html.'</div>');
                            })
                            ->html(),
                    ]),
            ]),

            Section::make('Aset yang Dipinjam')
                ->columnSpanFull()
                ->visible(fn (Loan $record) => $record->items()->exists())
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('asset.code')->label('Kode')->weight('medium'),
                            TextEntry::make('asset.name')->label('Nama Aset'),
                            TextEntry::make('quantity')
                                ->label('Jumlah')
                                ->formatStateUsing(fn ($state) => $state.' unit'),
                            TextEntry::make('condition_before')->label('Kondisi Awal')->badge()->placeholder('—'),
                            TextEntry::make('condition_after')
                                ->label('Kondisi Kembali')
                                ->badge()
                                ->placeholder('—')
                                ->visible(fn ($record) => ! $record?->isCollective()),
                            TextEntry::make('return_summary')
                                ->label('Hasil Kembali')
                                ->placeholder('—')
                                ->visible(fn ($record) => (bool) $record?->isCollective()),
                            TextEntry::make('damage_note')
                                ->label('Catatan Kerusakan')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Alat Massal')
                ->columnSpanFull()
                ->visible(fn (Loan $record) => $record->consumableItems()->exists())
                ->schema([
                    RepeatableEntry::make('consumableItems')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('consumable.name')->label('Alat')->weight('medium'),
                            TextEntry::make('quantity')
                                ->label('Dipinjam')
                                ->formatStateUsing(fn ($state, $record) => $state.' '.($record->consumable?->unit_of_measure ?? '')),
                            TextEntry::make('returned_quantity')->label('Kembali Baik')->placeholder('—'),
                            TextEntry::make('damaged_quantity')->label('Rusak')->placeholder('—'),
                            TextEntry::make('lost_quantity')
                                ->label('Hilang')
                                ->state(fn ($record) => $record->returned_quantity === null ? null : $record->lost_quantity)
                                ->placeholder('—'),
                            TextEntry::make('note')
                                ->label('Catatan')
                                ->placeholder('—')
                                ->columnSpanFull()
                                ->visible(fn ($record) => filled($record?->note)),
                        ]),
                ]),

            Section::make('Riwayat Pengingat')
                ->columnSpanFull()
                ->collapsed()
                ->visible(fn (Loan $record) => $record->reminders()->exists())
                ->schema([
                    TextEntry::make('riwayat_pengingat')
                        ->hiddenLabel()
                        ->state(function (Loan $record) {
                            $html = '<div style="width:100%">';

                            foreach ($record->reminders()->with('sender:id,name')->get() as $r) {
                                $html .= '<div style="width:100%;margin-bottom:12px;padding:12px;'
                                    .'border:1px solid #E3E8EF;border-radius:8px">'
                                    .'<p style="margin:0 0 6px;font-size:12px;color:#667085">'
                                    .$r->sent_at->locale('id')->translatedFormat('d F Y, H:i')
                                    .' · oleh '.e($r->sender?->name ?? '—')
                                    .' · ke '.e(Phone::display($r->phone))
                                    .'</p>'
                                    .'<p style="margin:0;font-size:13px;white-space:pre-wrap">'
                                    .e($r->message).'</p>'
                                    .'</div>';
                            }

                            return new HtmlString($html.'</div>');
                        })
                        ->html(),
                ]),
        ]);
    }
}
