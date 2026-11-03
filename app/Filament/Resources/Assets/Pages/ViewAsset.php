<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Services\QrService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
// use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Joaopaulolndev\FilamentPdfViewer\Infolists\Components\PdfViewerEntry;

// use Filament\Tables\Columns\Layout\Split;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
            Action::make('cetak_label')
                ->label('Cetak Label')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->url(fn () => route('label.single', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()->label('Ubah'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(4)->schema([
                /* -------------------- Informasi -------------------- */
                Section::make('Informasi Aset')
                    ->columnSpan(5)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')->label('Kode')->weight('bold')->copyable(),
                        TextEntry::make('name')->label('Nama'),
                        TextEntry::make('category.name')->label('Kategori'),
                        TextEntry::make('unit.name')->label('Unit'),
                        TextEntry::make('room.full_name')->label('Lokasi')->placeholder('—'),
                        TextEntry::make('condition')->label('Kondisi')->badge(),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('year_acquired')->label('Tahun'),
                        TextEntry::make('acquisition_value')->label('Nilai')->money('IDR')->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('serial_number')->label('Nomor Seri')->placeholder('—'),
                        TextEntry::make('brand')->label('Merek')->placeholder('—'),
                        TextEntry::make('model')->label('Model')->placeholder('—'),
                        TextEntry::make('vendor.name')->label('Vendor')->placeholder('—'),
                        TextEntry::make('warranty_until')->label('Garansi')->date('d/m/Y')->placeholder('—'),
                    ]),
            ]),
            Grid::make(6)->schema([
                /* ---------------------- Foto ----------------------- */
                Section::make('Foto Aset')
                    ->columnSpan(3)
                    ->schema([
                        Text::make(function (Asset $record) {
                            $url = $record->getFirstMediaUrl('foto');
                            if (! $url) {
                                return new HtmlString(
                                    '<div style="display:flex;flex-direction:column;align-items:center;'
                                    . 'justify-content:center;gap:8px;aspect-ratio:4/3;border-radius:8px;'
                                    . 'background:#F7F9FA;border:1px dashed #E3E8EF;color:#98A2B3;font-size:13px">'
                                    . 'Belum ada foto</div>'
                                );
                            }
                            $safe = e($url);
                            $id = 'foto-' . $record->getKey();
                            return new HtmlString(
                                '<img src="' . $safe . '" alt=""'
                                . ' onclick="document.getElementById(\'' . $id . '\').style.display=\'flex\'"'
                                . ' style="width:100%;height:auto;aspect-ratio:4/3;object-fit:cover;'
                                . 'border-radius:8px;cursor:zoom-in">'
                                . '<div id="' . $id . '"'
                                . ' onclick="this.style.display=\'none\'"'
                                . ' style="display:none;position:fixed;inset:0;z-index:9999;'
                                . 'background:rgba(16,24,40,.85);align-items:center;justify-content:center;'
                                . 'padding:24px;cursor:zoom-out">'
                                . '<img src="' . $safe . '" alt=""'
                                . ' style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px">'
                                . '</div>'
                            );
                        }),
                    ]),
                /* ----------------------- QR ------------------------ */
                Section::make('Kode QR')
                    ->columnSpan(3)
                    ->schema([
                        TextEntry::make('qr')
                            ->hiddenLabel()
                            ->state(fn (Asset $record) => new HtmlString(
                                '<div class="sapra-qr" style="width:100%;max-width:220px;margin:0 auto">'
                                . QrService::forAsset($record, 300)
                                . '<p style="text-align:center;font-family:monospace;font-size:13px;'
                                . 'margin-top:10px;letter-spacing:1px">'
                                . e($record->public_code)
                                . '</p></div>'
                                . '<style>.sapra-qr svg{width:100%;height:auto;display:block}</style>'
                            ))
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]),

            /* -------------------- Dokumen ---------------------- */
            Section::make('Dokumen')
                ->columnSpanFull()
                ->collapsed(true)
                ->schema([
                    PdfViewerEntry::make('dokumen')
                        ->getStateUsing(fn (Asset $record) => $record->getFirstMediaUrl('dokumen') ?: null)
                        ->minHeight('75svh')
                        ->columnSpanFull()
                        ->visible(fn (Asset $record) => $record->getMedia('dokumen')->isNotEmpty()),
                                        TextEntry::make('dokumen_kosong')
                        ->hiddenLabel()
                        ->state('Belum ada bukti dokumen')
                        ->weight('medium')
                        ->alignCenter()
                        ->columnSpanFull()
                        ->visible(fn (Asset $record) => $record->getMedia('dokumen')->isEmpty()),

                    TextEntry::make('dokumen_kosong_info')
                        ->hiddenLabel()
                        ->state('Unggah dokumen pendukung seperti faktur, BAST, atau kartu garansi melalui menu Edit.')
                        ->size('sm')
                        ->color('gray')
                        ->alignCenter()
                        ->columnSpanFull()
                        ->visible(fn (Asset $record) => $record->getMedia('dokumen')->isEmpty()),
                ]),
        ]);
    }
}