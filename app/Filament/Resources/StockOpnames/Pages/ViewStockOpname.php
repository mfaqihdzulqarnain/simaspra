<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Enums\OpnameItemStatus;
use App\Enums\AssetCondition;
use App\Enums\OpnameStatus;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\Room;
use App\Models\StockOpname;
use App\Services\OpnameService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /* -------------------- Mulai opname -------------------- */
            Action::make('mulai')
                ->label('Mulai Opname')
                ->icon(Heroicon::OutlinedPlayCircle)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Mulai Kegiatan Opname')
                ->modalDescription('Sistem akan membangkitkan daftar periksa dari seluruh aset dalam cakupan. Cakupan tidak dapat diubah setelah dimulai.')
                ->visible(fn (StockOpname $record) => $record->status === OpnameStatus::Draf
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (StockOpname $record) {
                    $count = OpnameService::start($record);

                    Notification::make()
                        ->success()
                        ->title('Opname dimulai')
                        ->body($count > 0
                            ? "{$count} aset masuk daftar periksa."
                            : 'Daftar periksa sudah ada sebelumnya.')
                        ->send();
                }),

            /* -------------------- Scan manual -------------------- */
            Action::make('scan')
                ->label('Scan / Input Kode')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('info')
                ->modalWidth('lg')
                ->modalHeading('Catat Hasil Pemeriksaan')
                ->modalDescription('Masukkan kode QR aset, atau gunakan alat pemindai.')
                ->schema([
                    TextInput::make('public_code')
                        ->label('Kode QR Aset')
                        ->placeholder('Contoh: R093FXRR')
                        ->required()
                        ->autofocus()
                        ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                    Select::make('found_room_id')
                        ->label('Ruang Tempat Ditemukan')
                        ->options(fn () => Room::query()
                            ->with('building')
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn (Room $r) => [$r->id => $r->full_name]))
                        ->searchable()
                        ->native(false),

                    Select::make('condition')
                        ->label('Kondisi Fisik')
                        ->options(AssetCondition::class)
                        ->native(false),
                    
                    TextInput::make('found_quantity')
                        ->label('Jumlah Ditemukan')
                        ->helperText('Khusus aset kolektif, misalnya kursi lipat. Kosongkan untuk aset biasa atau bila jumlahnya sesuai catatan.')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->suffix('unit'),

                    Textarea::make('note')->label('Catatan')->rows(2),
                ])
                ->visible(fn (StockOpname $record) => $record->status === OpnameStatus::Berjalan)
                ->action(function (StockOpname $record, array $data) {
                    $result = OpnameService::scan(
                        $record,
                        strtoupper(trim($data['public_code'])),
                        $data['found_room_id'] ?? null,
                        OpnameService::toCondition($data['condition'] ?? null),
                        $data['note'] ?? null,
                        filled($data['found_quantity'] ?? null) ? (int) $data['found_quantity'] : null,
                    );

                    Notification::make()
                        ->status($result['ok'] ? 'success' : 'danger')
                        ->title($result['ok'] ? 'Hasil dicatat' : 'Gagal')
                        ->body($result['message'])
                        ->send();
                }),

            /* ------------- Barang tak tercatat ------------- */
            Action::make('barang_baru')
                ->label('Barang Belum Tercatat')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('warning')
                ->modalWidth('lg')
                ->modalHeading('Catat Barang Belum Terdaftar')
                ->modalDescription('Gunakan untuk barang yang ditemukan di ruangan tetapi belum ada di sistem.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Barang')
                        ->required()
                        ->maxLength(150),

                    Select::make('room_id')
                        ->label('Ruang')
                        ->options(fn () => Room::query()
                            ->with('building')
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn (Room $r) => [$r->id => $r->full_name]))
                        ->required()
                        ->searchable()
                        ->native(false),

                    Textarea::make('note')->label('Keterangan')->rows(2),
                ])
                ->visible(fn (StockOpname $record) => $record->status === OpnameStatus::Berjalan)
                ->action(function (StockOpname $record, array $data) {
                    OpnameService::recordUnregistered(
                        $record,
                        $data['name'],
                        $data['room_id'],
                        $data['note'] ?? null,
                    );

                    Notification::make()->success()->title('Barang dicatat sebagai temuan')->send();
                }),

            /* -------------------- Tutup opname -------------------- */
            Action::make('tutup')
                ->label('Tutup Opname')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('success')
                ->modalWidth('lg')
                ->modalHeading('Tutup Kegiatan Opname')
                ->modalDescription(function (StockOpname $record) {
                    // Selisih jumlah aset kolektif yang belum diberi tindak lanjut
                    $tanpaTindakLanjut = $record->items()
                        ->where('status', OpnameItemStatus::BedaJumlah)
                        ->whereNull('quantity_follow_up')
                        ->count();

                    return 'Setelah ditutup, hasil tidak dapat diubah lagi.'
                        .($tanpaTindakLanjut
                            ? ' Ada '.$tanpaTindakLanjut.' selisih jumlah aset kolektif tanpa tindak lanjut; selisihnya hanya tercatat di berita acara.'
                            : '');
                })
                ->schema([
                    Toggle::make('apply_changes')
                        ->label('Terapkan temuan ke data aset')
                        ->helperText('Lokasi dan kondisi aset diperbarui sesuai hasil pemeriksaan. Selisih jumlah aset kolektif dijalankan sesuai tindak lanjut yang dipilih.')
                        ->default(true),
                ])
                ->visible(fn (StockOpname $record) => $record->status === OpnameStatus::Berjalan
                    && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (StockOpname $record, array $data) {
                    $result = OpnameService::close($record, $data['apply_changes'] ?? true);

                    Notification::make()
                        ->success()
                        ->title('Opname ditutup')
                        ->body($result['applied'] > 0
                            ? $result['applied'].' data aset diperbarui.'
                            : 'Tidak ada data aset yang diubah.')
                        ->persistent()
                        ->send();
                }),

            Action::make('cetak')
                ->label('Cetak Berita Acara')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (StockOpname $record) => route('opname.print', $record))
                ->openUrlInNewTab()
                ->visible(fn (StockOpname $record) => $record->status !== OpnameStatus::Draf),

            EditAction::make()->label('Ubah')
                ->visible(fn (StockOpname $record) => $record->status === OpnameStatus::Draf),

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
                Section::make('Informasi Kegiatan')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')->label('Kode')->weight('bold'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('name')->label('Nama Kegiatan')->columnSpanFull(),
                        TextEntry::make('period_start')->label('Mulai')->date('d/m/Y'),
                        TextEntry::make('period_end')->label('Selesai')->date('d/m/Y'),
                        TextEntry::make('unit.name')->label('Unit')->placeholder('Seluruh unit'),
                        TextEntry::make('building.name')->label('Gedung')->placeholder('Seluruh gedung'),
                        TextEntry::make('creator.name')->label('Dibuat Oleh'),
                        TextEntry::make('closed_at')->label('Ditutup')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('note')->label('Catatan')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Ringkasan Hasil')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('ringkasan')
                            ->hiddenLabel()
                            ->state(function (StockOpname $record) {
                                $r = OpnameService::report($record);

                                $baris = [
                                    ['Total aset', $r['total'], '#101828'],
                                    ['Sudah diperiksa', $r['diperiksa'], '#2E90FA'],
                                    ['Sesuai', $r['ditemukan'], '#12B76A'],
                                    ['Tidak ditemukan', $r['tidak_ditemukan'], '#D92D20'],
                                    ['Beda lokasi', $r['beda_lokasi'], '#F79009'],
                                    ['Beda kondisi', $r['beda_kondisi'], '#F79009'],
                                    ['Belum tercatat', $r['tidak_tercatat'], '#2E90FA'],
                                ];

                                $html = '<div style="width:100%">';

                                foreach ($baris as [$label, $nilai, $warna]) {
                                    $html .= '<div style="display:flex;justify-content:space-between;'
                                        .'padding:6px 0;border-bottom:1px solid #F2F4F7">'
                                        .'<span style="font-size:13px;color:#667085">'.$label.'</span>'
                                        .'<span style="font-size:14px;font-weight:600;color:'.$warna.'">'
                                        .$nilai.'</span>'
                                        .'</div>';
                                }

                                $persen = $record->progress_percent;

                                $html .= '<div style="margin-top:14px">'
                                    .'<div style="display:flex;justify-content:space-between;margin-bottom:6px">'
                                    .'<span style="font-size:12px;color:#667085">Progres</span>'
                                    .'<span style="font-size:12px;font-weight:600">'.$persen.'%</span>'
                                    .'</div>'
                                    .'<div style="height:8px;border-radius:4px;background:#F2F4F7;overflow:hidden">'
                                    .'<div style="height:8px;width:'.$persen.'%;background:#14315C"></div>'
                                    .'</div></div>';

                                return new HtmlString($html.'</div>');
                            })
                            ->html(),
                    ]),
            ]),
        ]);
    }
}
