<?php

namespace App\Filament\Resources\Disposals\Pages;

use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Filament\Resources\Disposals\DisposalResource;
use App\Models\Disposal;
use App\Services\DisposalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class ViewDisposal extends ViewRecord
{
    protected static string $resource = DisposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
                
            /* ---------------- Unit mengirim usulan ---------------- */
            Action::make('ajukan')
                ->label('Kirim Usulan')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Kirim Usulan Penghapusan')
                ->modalDescription('Aset yang diusulkan akan ditandai "Diusulkan Dihapus" dan tidak dapat dipinjam.')
                ->visible(fn (Disposal $record) => $record->status === DisposalStatus::Usulan
                    && $record->items()->exists()
                    && ($record->proposed_by === Auth::id() || Auth::user()?->canSeeAllUnits()))
                ->action(function (Disposal $record) {
                    DisposalService::submit($record);

                    Notification::make()
                        ->success()
                        ->title('Usulan terkirim')
                        ->body('Nomor: '.$record->disposal_number)
                        ->send();
                }),

            /* ------------- Ka. Sarpras memeriksa fisik ------------- */
            Action::make('periksa')
                ->label('Selesai Periksa & Teruskan')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('success')
                ->modalWidth('2xl')
                ->modalHeading('Hasil Pemeriksaan Fisik')
                ->schema([
                    Textarea::make('note')
                        ->label('Catatan Hasil Pemeriksaan')
                        ->helperText('Jelaskan kondisi fisik barang yang diperiksa.')
                        ->rows(4)
                        ->required(),
                ])
                ->visible(fn (Disposal $record) => $record->status === DisposalStatus::Pemeriksaan
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (Disposal $record, array $data) {
                    DisposalService::inspect($record, $data['note']);

                    Notification::make()->success()->title('Diteruskan ke Wadir')->send();
                }),

            /* -------------------- Wadir menyetujui -------------------- */
            Action::make('setujui')
                ->label('Setujui Penghapusan')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Setujui Penghapusan')
                ->modalDescription('Setelah disetujui, Bagian Sarpras dapat mengeksekusi penghapusan.')
                ->schema([
                    Textarea::make('note')->label('Catatan Persetujuan')->rows(2),
                ])
                ->visible(fn (Disposal $record) => $record->status === DisposalStatus::MenungguWadir
                    && Auth::user()?->hasAnyRole(['wadir', 'super_admin']))
                ->action(function (Disposal $record, array $data) {
                    DisposalService::approve($record, $data['note'] ?? null);

                    Notification::make()->success()->title('Penghapusan disetujui')->send();
                }),

            /* ----------------------- Menolak ----------------------- */
            Action::make('tolak')
                ->label('Tolak')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->modalHeading('Tolak Usulan Penghapusan')
                ->modalDescription('Aset akan dikembalikan ke status Aktif.')
                ->schema([
                    Textarea::make('reason')->label('Alasan Penolakan')->rows(3)->required(),
                ])
                ->visible(fn (Disposal $record) => in_array($record->status, [
                    DisposalStatus::Pemeriksaan,
                    DisposalStatus::MenungguWadir,
                ], true) && Auth::user()?->hasAnyRole(['kabag_sarpras', 'wadir', 'super_admin']))
                ->action(function (Disposal $record, array $data) {
                    $role = $record->status === DisposalStatus::MenungguWadir ? 'wadir' : 'kabag_sarpras';

                    DisposalService::reject($record, $data['reason'], $role);

                    Notification::make()->danger()->title('Usulan ditolak')->send();
                }),

            /* --------------------- Eksekusi --------------------- */
            Action::make('eksekusi')
                ->label('Eksekusi Penghapusan')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->modalWidth('2xl')
                ->modalHeading('Eksekusi Penghapusan Aset')
                ->modalDescription('Tindakan ini mengubah status aset menjadi Dihapuskan secara permanen. Data aset tetap tersimpan untuk keperluan audit, tetapi tidak dapat dikembalikan ke status aktif.')
                ->modalSubmitActionLabel('Eksekusi & Buat Berita Acara')
                ->schema([
                    Select::make('method')
                        ->label('Metode Penghapusan')
                        ->options(DisposalMethod::class)
                        ->required()
                        ->live()
                        ->native(false),

                    DatePicker::make('executed_at')
                        ->label('Tanggal Eksekusi')
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    TextInput::make('revenue_amount')
                        ->label('Nilai Pemasukan')
                        ->helperText('Hasil penjualan atau nilai tukar guling.')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->prefix('Rp')
                        ->visible(fn (Get $get) => in_array($get('method'), [
                            DisposalMethod::Jual->value,
                            DisposalMethod::TukarGuling->value,
                        ], true))
                        ->required(fn (Get $get) => in_array($get('method'), [
                            DisposalMethod::Jual->value,
                            DisposalMethod::TukarGuling->value,
                        ], true)),

                    Textarea::make('execution_note')
                        ->label('Catatan Pelaksanaan')
                        ->rows(3),
                ])
                ->visible(fn (Disposal $record) => $record->status === DisposalStatus::Disetujui
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (Disposal $record, array $data) {
                    $handover = DisposalService::execute($record, $data);

                    Notification::make()
                        ->success()
                        ->title('Penghapusan dieksekusi')
                        ->body('Berita acara '.$handover->number.' dibuat. '
                            .$record->items()->count().' aset dihapuskan.')
                        ->persistent()
                        ->send();
                }),

            /* ---------------------- Arsipkan ---------------------- */
            Action::make('arsip')
                ->label('Arsipkan')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Arsipkan Berkas')
                ->modalDescription('Gunakan setelah dokumen fisik lengkap dan ditandatangani.')
                ->visible(fn (Disposal $record) => $record->status === DisposalStatus::Dieksekusi
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (Disposal $record) {
                    DisposalService::archive($record);

                    Notification::make()->success()->title('Berkas diarsipkan')->send();
                }),

            EditAction::make()->label('')
                ->visible(fn (Disposal $record) => $record->isEditable()),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Grid::make(2)->schema([
                Section::make('Data Penghapusan')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('disposal_number')->label('Nomor')->weight('bold'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('unit.name')->label('Unit Pengusul'),
                        TextEntry::make('proposer.name')->label('Diusulkan Oleh'),
                        TextEntry::make('reason')->label('Alasan')->badge(),
                        TextEntry::make('method')->label('Metode')->badge()->placeholder('—'),
                        TextEntry::make('total_book_value')
                            ->label('Total Nilai Buku')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('revenue_amount')
                            ->label('Pemasukan')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('executed_at')->label('Tanggal Eksekusi')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('inspector.name')->label('Diperiksa Oleh')->placeholder('—'),
                        TextEntry::make('description')->label('Uraian Alasan')->columnSpanFull(),
                        TextEntry::make('inspection_note')
                            ->label('Hasil Pemeriksaan Fisik')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('execution_note')
                            ->label('Catatan Pelaksanaan')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->visible(fn (Disposal $record) => filled($record->execution_note)),
                        TextEntry::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->columnSpanFull()
                            ->visible(fn (Disposal $record) => $record->status === DisposalStatus::Ditolak),
                    ]),
            ]),
            Grid::make(1)->schema([
                Section::make('Riwayat Persetujuan')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('riwayat')
                            ->hiddenLabel()
                            ->state(function (Disposal $record) {
                                $approvals = $record->approvals()->with('user')->orderBy('acted_at')->get();

                                if ($approvals->isEmpty()) {
                                    return new HtmlString(
                                        '<p style="font-size:13px;color:#667085">Belum ada tindakan.</p>'
                                    );
                                }

                                $html = '<div style="width:100%">';

                                foreach ($approvals as $a) {
                                    $color = match ($a->action->getColor()) {
                                        'success' => '#12B76A',
                                        'danger' => '#D92D20',
                                        default => '#F79009',
                                    };

                                    $html .= '<div style="border-left:3px solid '.$color.';'
                                        .'padding-left:10px;margin-bottom:14px">'
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

                                $html .= '</div>';

                                // Tautan berita acara
                                $handovers = $record->handovers()->get();

                                if ($handovers->isNotEmpty()) {
                                    $html .= '<div style="width:100%;margin-top:8px;padding-top:12px;'
                                        .'border-top:1px solid #E3E8EF">';

                                    foreach ($handovers as $h) {
                                        $html .= '<a href="'.route('bast.print', $h).'"'
                                            .' target="_blank" rel="noopener"'
                                            .' style="display:block;font-size:13px;color:#2E90FA;'
                                            .'text-decoration:none;font-weight:500">'
                                            .'Cetak '.e($h->number).'</a>';
                                    }

                                    $html .= '</div>';
                                }

                                return new HtmlString($html);
                            })
                            ->html(),
                    ]), Grid::make(2)->schema([
                        Section::make('Aset yang Dihapuskan')
                            ->columnSpanFull()
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->hiddenLabel()
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('asset.code')->label('Kode Aset')->weight('medium'),
                                        TextEntry::make('asset.name')->label('Nama Aset'),
                                        TextEntry::make('condition')->label('Kondisi')->badge(),
                                        TextEntry::make('book_value')
                                            ->label('Nilai Buku')
                                            ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                                        TextEntry::make('asset.status')
                                            ->label('Status Aset Sekarang')
                                            ->badge()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}