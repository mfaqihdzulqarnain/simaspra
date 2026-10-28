<?php

namespace App\Filament\Resources\ProcurementRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\ProcurementRequests\ProcurementRequestResource;
use App\Models\Contract;
use App\Models\ProcurementRequest;
use App\Models\Room;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DistributionService;
use App\Services\ProcurementService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewProcurementRequest extends ViewRecord
{
    protected static string $resource = ProcurementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),

            /* ---------------- Unit mengajukan ---------------- */
            Action::make('ajukan')
                ->label('Ajukan')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Kirim Pengajuan')
                ->modalDescription('Setelah dikirim, pengajuan tidak dapat diubah kecuali dikembalikan untuk revisi.')
                ->visible(fn (ProcurementRequest $record) => $record->isEditable()
                    && $record->items()->exists()
                    && ($record->requested_by === Auth::id() || Auth::user()?->canSeeAllUnits()))
                ->action(function (ProcurementRequest $record) {
                    ProcurementService::submit($record);

                    Notification::make()
                        ->success()
                        ->title('Pengajuan terkirim')
                        ->body('Nomor: '.$record->fresh()->request_number)
                        ->send();
                }),

            /* ------------- Ka. Sarpras memverifikasi ------------- */
            Action::make('verifikasi')
                ->label('Verifikasi & Teruskan')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->schema([
                    Textarea::make('note')->label('Catatan Verifikasi')->rows(3),
                ])
                ->visible(fn (ProcurementRequest $record) => $record->status === RequestStatus::VerifikasiSarpras
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (ProcurementRequest $record, array $data) {
                    ProcurementService::verify($record, $data['note'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Diteruskan ke Wadir')
                        ->send();
                }),

            Action::make('revisi')
                ->label('Minta Revisi')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->schema([
                    Textarea::make('note')
                        ->label('Apa yang perlu diperbaiki?')
                        ->rows(3)
                        ->required(),
                ])
                ->visible(fn (ProcurementRequest $record) => $record->status === RequestStatus::VerifikasiSarpras
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (ProcurementRequest $record, array $data) {
                    ProcurementService::requestRevision($record, $data['note']);

                    Notification::make()
                        ->warning()
                        ->title('Dikembalikan untuk revisi')
                        ->send();
                }),

            /* ---------------- Wadir menyetujui ---------------- */
            Action::make('setujui')
                ->label('Setujui')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->schema([
                    Textarea::make('note')->label('Catatan Persetujuan')->rows(3),
                ])
                ->visible(fn (ProcurementRequest $record) => $record->status === RequestStatus::MenungguWadir
                    && Auth::user()?->hasAnyRole(['wadir', 'super_admin']))
                ->action(function (ProcurementRequest $record, array $data) {
                    ProcurementService::approve($record, $data['note'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Pengajuan disetujui')
                        ->send();
                }),

            /* -------------------- Menolak -------------------- */
            Action::make('tolak')
                ->label('Tolak')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->schema([
                    Textarea::make('reason')
                        ->label('Alasan Penolakan')
                        ->rows(3)
                        ->required(),
                ])
                ->visible(fn (ProcurementRequest $record) => in_array($record->status, [
                    RequestStatus::VerifikasiSarpras,
                    RequestStatus::MenungguWadir,
                ], true) && Auth::user()?->hasAnyRole(['kabag_sarpras', 'wadir', 'super_admin']))
                ->action(function (ProcurementRequest $record, array $data) {
                    $role = $record->status === RequestStatus::MenungguWadir ? 'wadir' : 'kabag_sarpras';

                    ProcurementService::reject($record, $data['reason'], $role);

                    Notification::make()
                        ->danger()
                        ->title('Pengajuan ditolak')
                        ->send();
                }),
            /* ---------------- Ka. Sarpras menyalurkan ---------------- */
            Action::make('salurkan')
                ->label('Salurkan Aset')
                ->icon(Heroicon::OutlinedTruck)
                ->color('success')
                ->modalWidth('5xl')
                ->modalHeading('Penyaluran Aset')
                ->modalDescription('Isi data perolehan. Setiap barang akan dibuatkan kode aset dan QR sendiri.')
                ->modalSubmitActionLabel('Salurkan & Buat BAST')
                ->visible(fn (ProcurementRequest $record) => $record->status === RequestStatus::Disetujui
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->fillForm(fn (ProcurementRequest $record) => [
                    'to_user_id' => $record->requested_by,
                    'handover_date' => now()->toDateString(),
                    'rows' => $record->items->map(fn ($item) => [
                        'item_id' => $item->getKey(),
                        'item_name' => $item->name.' ('.$item->quantity.' '.$item->unit_of_measure.')',
                        'quantity' => $item->quantity,
                        'acquisition_value' => $item->estimated_unit_price,
                        'acquisition_date' => now()->toDateString(),
                    ])->all(),
                ])
                ->schema([
                    Section::make('Penerima')
                        ->columns(2)
                        ->schema([
                            Select::make('to_user_id')
                                ->label('Diserahkan Kepada')
                                ->options(fn (ProcurementRequest $record) => User::query()
                                    ->where(function ($q) use ($record) {
                                        $q->where('unit_id', $record->unit_id)
                                          ->orWhere('id', $record->requested_by);
                                    })
                                    ->where('is_active', true)
                                    ->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->native(false),

                            DatePicker::make('handover_date')
                                ->label('Tanggal Serah Terima')
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y'),

                            Textarea::make('notes')
                                ->label('Catatan')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),

                    Section::make('Rincian Barang')
                        ->schema([
                            Repeater::make('rows')
                                ->hiddenLabel()
                                ->columns(3)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->itemLabel(fn (array $state) => $state['item_name'] ?? null)
                                ->schema([
                                    Hidden::make('item_id'),
                                    Hidden::make('item_name'),

                                    TextInput::make('quantity')
                                        ->label('Jumlah Diterima')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(50)
                                        ->required()
                                        ->helperText('Boleh kurang dari yang diajukan.'),

                                    Select::make('room_id')
                                        ->label('Ditempatkan di Ruang')
                                        ->options(fn () => Room::query()
                                            ->with('building')
                                            ->where('is_active', true)
                                            ->get()
                                            ->mapWithKeys(fn ($r) => [$r->id => $r->full_name]))
                                        ->searchable()
                                        ->native(false),

                                    DatePicker::make('acquisition_date')
                                        ->label('Tanggal Perolehan')
                                        ->required()
                                        ->native(false)
                                        ->displayFormat('d/m/Y'),

                                    TextInput::make('acquisition_value')
                                        ->label('Harga Satuan Riil')
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('Rp')
                                        ->required(),

                                    Select::make('vendor_id')
                                        ->label('Vendor')
                                        ->options(fn () => Vendor::where('is_active', true)->pluck('name', 'id'))
                                        ->searchable()
                                        ->native(false),

                                    DatePicker::make('warranty_until')
                                        ->label('Garansi Sampai')
                                        ->native(false)
                                        ->displayFormat('d/m/Y'),

                                    Select::make('contract_id')
                                        ->label('Kontrak')
                                        ->options(fn () => Contract::pluck('number', 'id'))
                                        ->searchable()
                                        ->native(false),
                                ]),
                        ]),
                ])
                ->action(function (ProcurementRequest $record, array $data) {
                    $handover = DistributionService::distribute(
                        $record,
                        $data['rows'],
                        $data['to_user_id'],
                        $data['handover_date'],
                        $data['notes'] ?? null,
                    );

                    Notification::make()
                        ->success()
                        ->title('Aset berhasil disalurkan')
                        ->body('BAST '.$handover->number.' dibuat. '.$handover->items()->count().' aset terdaftar.')
                        ->persistent()
                        ->send();
                }),

            EditAction::make()->label('')
                ->visible(fn (ProcurementRequest $record) => $record->isEditable()),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Grid::make(3)->schema([
                Section::make('Informasi Pengajuan')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('request_number')->label('Nomor')->weight('bold')->placeholder('(draf)'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('unit.name')->label('Unit Pengaju'),
                        TextEntry::make('requester.name')->label('Diajukan Oleh'),
                        TextEntry::make('budget_year')->label('Tahun Anggaran'),
                        TextEntry::make('priority')->label('Prioritas')->badge(),
                        TextEntry::make('needed_at')->label('Dibutuhkan Sebelum')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('total_estimated')
                            ->label('Perkiraan Total')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('purpose')->label('Tujuan')->columnSpanFull(),
                    ]),

                Section::make('Riwayat Persetujuan')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('riwayat')
                            ->hiddenLabel()
                            ->state(function (ProcurementRequest $record) {
                                $approvals = $record->approvals()->with('user')->orderBy('acted_at')->get();

                                if ($approvals->isEmpty()) {
                                    return new HtmlString(
                                        '<p style="font-size:13px;color:#667085">Belum ada tindakan.</p>'
                                    );
                                }

                                $html = '<div style="display:flex;flex-direction:column;gap:14px">';

                                foreach ($approvals as $a) {
                                    $color = match ($a->action->getColor()) {
                                        'success' => '#12B76A',
                                        'danger' => '#D92D20',
                                        default => '#F79009',
                                    };

                                    $html .= '<div style="border-left:3px solid '.$color.';padding-left:10px">'
                                        .'<p style="margin:0;font-size:13px;font-weight:600">'
                                        .e($a->action->getLabel()).'</p>'
                                        .'<p style="margin:2px 0 0;font-size:12px;color:#667085">'
                                        .e($a->user?->name ?? '—').' · '
                                        .$a->acted_at->translatedFormat('d M Y H:i').'</p>'
                                        .($a->note
                                            ? '<p style="margin:4px 0 0;font-size:12px;color:#344054">'
                                                .e($a->note).'</p>'
                                            : '')
                                        .'</div>';
                                }

                                return new HtmlString($html.'</div>');
                            })
                            ->html(),
                    ]),
            ]),

            Section::make('Daftar Barang')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('name')->label('Nama Barang')->columnSpan(2),
                            TextEntry::make('category.name')->label('Kategori'),
                            TextEntry::make('quantity')
                                ->label('Jumlah')
                                ->formatStateUsing(fn ($state, $record) => $state.' '.$record->unit_of_measure),
                            TextEntry::make('estimated_total')
                                ->label('Perkiraan')
                                ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                            TextEntry::make('specification')
                                ->label('Spesifikasi')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make('Berita Acara Serah Terima')
                ->columnSpanFull()
                ->visible(fn (ProcurementRequest $record) => $record->handovers()->exists())
                ->schema([
                    TextEntry::make('bast')
                        ->hiddenLabel()
                        ->state(function (ProcurementRequest $record) {
                            $handovers = $record->handovers()->with('items.asset')->get();

                            $html = '<div style="width:100%">';

                            foreach ($handovers as $h) {
                                $html .= '<div style="width:100%;margin-bottom:16px;padding:14px;'
                                    .'border:1px solid #E3E8EF;border-radius:10px">'
                                    .'<div style="display:flex;align-items:center;justify-content:space-between;'
                                    .'gap:12px;flex-wrap:wrap">'
                                    .'<div>'
                                    .'<p style="margin:0;font-size:14px;font-weight:600">'.e($h->number).'</p>'
                                    .'<p style="margin:2px 0 0;font-size:12px;color:#667085">'
                                    .$h->handover_date->translatedFormat('d F Y')
                                    .' · '.$h->items->count().' aset</p>'
                                    .'</div>'
                                    .'<a href="'.route('bast.print', $h).'" target="_blank" rel="noopener"'
                                    .' style="font-size:13px;color:#2E90FA;text-decoration:none;font-weight:500">'
                                    .'Cetak BAST</a>'
                                    .'</div>';

                                $html .= '<div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px">';

                                foreach ($h->items as $it) {
                                    $html .= '<span style="font-size:11px;font-family:monospace;'
                                        .'padding:3px 8px;border-radius:5px;background:#EEF3F9;color:#14315C">'
                                        .e($it->asset?->code ?? '—').'</span>';
                                }

                                $html .= '</div></div>';
                            }

                            return new HtmlString($html.'</div>');
                        })
                        ->html(),
                ]),
        ]);
    }
}