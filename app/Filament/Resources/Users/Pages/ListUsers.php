<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Imports\UserImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impor')
                ->label('Impor Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('info')
                ->modalWidth('lg')
                ->modalHeading('Impor Dosen & Mahasiswa')
                ->modalSubmitActionLabel('Mulai Impor')
                ->visible(fn () => Auth::user()?->hasAnyRole(['super_admin', 'kabag_sarpras']))
                ->schema([
                    Placeholder::make('panduan')
                        ->hiddenLabel()
                        ->content(new HtmlString(
                            '<div style="border:1px solid #E3E8EF;border-radius:10px;padding:14px;background:#F7F9FA">'
                            . '<p style="margin:0 0 6px;font-size:13px;font-weight:600">Langkah 1 — Siapkan berkas</p>'
                            . '<p style="margin:0 0 10px;font-size:12px;color:#667085;line-height:1.55">'
                            . 'Kolom <b>peran</b> diisi salah satu: mahasiswa, dosen, laboran, staf_ruang, teknisi.<br>'
                            . 'Kata sandi awal otomatis sama dengan <b>NIM</b>, atau <b>NIP</b> bila NIM kosong.<br>'
                            . 'Email yang sudah terdaftar akan diperbarui datanya, kata sandinya tidak diubah.</p>'
                            . '<a href="' . route('user.template') . '"'
                            . ' style="display:inline-flex;align-items:center;gap:6px;padding:7px 12px;'
                            . 'border:1px solid #E3E8EF;border-radius:8px;background:#fff;'
                            . 'font-size:12px;font-weight:500;color:#14315C;text-decoration:none">'
                            . 'Unduh Templat Excel</a>'
                            . '</div>'
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
                    $import = new UserImport;

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

                    $ringkas = collect([
                        $import->dibuat > 0 ? $import->dibuat . ' akun baru' : null,
                        $import->diperbarui > 0 ? $import->diperbarui . ' akun diperbarui' : null,
                    ])->filter()->implode(', ') ?: 'Tidak ada data diproses';

                    $notif = Notification::make()->title($ringkas);

                    if (count($import->gagal)) {
                        $notif->warning()
                            ->body(count($import->gagal) . ' baris gagal: '
                                . implode(' | ', array_slice($import->gagal, 0, 5))
                                . (count($import->gagal) > 5 ? ' ...' : ''))
                            ->persistent();
                    } else {
                        $notif->success();
                    }

                    $notif->send();
                }),

            CreateAction::make()
                ->label('Tambah Pengguna')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),

            'mahasiswa' => Tab::make('Mahasiswa')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('roles', fn ($q) => $q->where('name', 'mahasiswa'))),

            'dosen' => Tab::make('Dosen')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['dosen', 'kaprodi']))),

            'nonaktif' => Tab::make('Nonaktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', false)),
        ];
    }
}