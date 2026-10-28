<?php

namespace App\Filament\Resources\Assets\Pages;

// use App\Exports\AssetTemplateExport;
use App\Filament\Resources\Assets\AssetResource;
use App\Imports\AssetImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impor')
                ->label('Impor Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('info')
                ->modalWidth('lg')
                ->modalHeading('Impor Data Aset')
                ->modalSubmitActionLabel('Mulai Impor')
                ->visible(fn () => Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->schema([
                    Placeholder::make('panduan')
                        ->hiddenLabel()
                        ->content(new HtmlString(
                            '<div style="border:1px solid #E3E8EF;border-radius:10px;padding:14px;background:#F7F9FA">'
                            .'<p style="margin:0 0 6px;font-size:13px;font-weight:600">Langkah 1 — Siapkan berkas</p>'
                            .'<p style="margin:0 0 10px;font-size:12px;color:#667085;line-height:1.5">'
                            .'Unduh templat, isi datanya, lalu unggah kembali. '
                            .'Kode unit, kategori, ruang, dan vendor harus sudah terdaftar di sistem.</p>'
                            . 'Kolom <b>jumlah</b> diisi bila ada beberapa unit identik: satu baris '
                            . 'menghasilkan sebanyak itu aset, masing-masing berkode sendiri (maks. 200).<br>'
                            . 'Isi <b>kolektif = Ya</b> untuk barang yang cukup satu kode QR, misalnya kursi lipat: '
                            . 'satu baris menjadi satu aset berisi seluruh unitnya.<br>'
                            . 'Nilai perolehan diisi <b>per unit</b>, bukan total.<br>'
                            .'<a href="'.route('asset.template').'"'
                            .' style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;'
                            .'border:1px solid #fff;border-radius:8px;background:green;'
                            .'font-size:12px;font-weight:500;color:#ffffff;text-decoration:none">'
                            .'Unduh Templat Excel</a>'
                            .'</div>'
                        )),

                    FileUpload::make('berkas')
                        ->label('Langkah 2 — Unggah Berkas')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->maxSize(8192)
                        ->disk('local')
                        ->directory('impor')
                        ->helperText('Format .xlsx, .xls, atau .csv. Maksimal 8 MB.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['berkas']);

                    $import = new AssetImport;

                    try {
                        Excel::import($import, $path);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Impor gagal')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['berkas']);
                    }

                    $notif = Notification::make()
                        ->title($import->berhasil.' aset berhasil diimpor');

                    $pesan = [];

                    if (count($import->gagal)) {
                        $pesan[] = count($import->gagal).' baris gagal: '
                            .implode(' | ', array_slice($import->gagal, 0, 5))
                            .(count($import->gagal) > 5 ? ' ...' : '');
                    }

                    if (count($import->catatan)) {
                        $pesan[] = implode(' ', array_slice($import->catatan, 0, 10));
                    }

                    if ($pesan) {
                        $notif->warning()->body(implode(' — ', $pesan))->persistent();
                    } else {
                        $notif->success();
                    }

                    $notif->send();
                }),

            CreateAction::make()
                ->label('Tambah Aset')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }
}
