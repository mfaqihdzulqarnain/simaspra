<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use App\Support\Sapra;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class ManageGeneralSettings extends SettingsPage
{
    // Akses mengikuti izin halaman di menu Peran (Shield)
    use HasPageShield;
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Sistem';

    protected static ?string $title = 'Pengaturan Sistem';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 90;

    protected static string $settings = GeneralSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Pengaturan')
                ->columnSpanFull()
                ->tabs([

                    /* ------------------------- Identitas ------------------------- */
                    Tabs\Tab::make('Identitas')
                        ->icon(Heroicon::OutlinedBuildingLibrary)
                        ->schema([
                            TextInput::make('app_name')
                                ->label('Nama Sistem')
                                ->helperText('Tampil di judul halaman dan kop dokumen.')
                                ->required()
                                ->maxLength(60),

                            Grid::make(2)->schema([
                                TextInput::make('institution_name')
                                    ->label('Nama Instansi')
                                    ->required()
                                    ->maxLength(120),

                                TextInput::make('institution_short')
                                    ->label('Nama Singkat')
                                    ->helperText('Dipakai di stiker aset yang ruangnya sempit.')
                                    ->required()
                                    ->maxLength(40),

                                TextInput::make('hero_tagline')
                                    ->label('Tagline Halaman Depan')
                                    ->maxLength(150)
                                    ->columnSpanFull(),

                                Toggle::make('show_asset_value')
                                    ->label('Tampilkan total nilai aset di halaman publik')
                                    ->helperText('Nonaktifkan bila nilai aset dianggap informasi sensitif.')
                                    ->columnSpanFull(),
                            ]),

                            Textarea::make('address')
                                ->label('Alamat')
                                ->rows(2)
                                ->maxLength(255),

                            Grid::make(3)->schema([
                                TextInput::make('phone')
                                    ->label('Telepon')
                                    ->tel()
                                    ->maxLength(20),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(120),

                                TextInput::make('website')
                                    ->label('Website')
                                    ->url()
                                    ->maxLength(120),
                            ]),
                        ]),

                    Section::make('Template Pesan Pengingat')
                        ->description('Dipakai laboran saat mengirim pengingat keterlambatan lewat WhatsApp.')
                        ->schema([
                            Textarea::make('reminder_template')
                                ->label('Isi Pesan')
                                ->rows(10)
                                ->columnSpanFull()
                                ->helperText(new HtmlString(
                                    'Kata kunci yang diganti otomatis: '
                                    .'<code>{nama}</code> <code>{nomor}</code> <code>{alat}</code> '
                                    .'<code>{lab}</code> <code>{batas}</code> <code>{terlambat}</code> '
                                    .'<code>{instansi}</code>'
                                )),
                        ]),

                    /* ----------------------- Logo & Warna ------------------------ */
                    Tabs\Tab::make('Logo & Warna')
                        ->icon(Heroicon::OutlinedPaintBrush)
                        ->schema([
                            Section::make('Tema Tampilan')
                                ->description('Berlaku untuk panel admin dan halaman publik.')
                                ->schema([
                                    Radio::make('theme_preset')
                                        ->label('Pilihan Tema')
                                        ->options(collect(config('sapra.themes'))
                                            ->mapWithKeys(fn ($t, $k) => [$k => $t['label']])
                                            ->all())
                                        ->descriptions([
                                            'navy' => 'Formal dan tegas. Cocok untuk institusi pendidikan.',
                                            'teal' => 'Segar dan modern, tetap tenang.',
                                            'slate' => 'Netral, tidak bersaing dengan warna logo.',
                                        ])
                                        ->default('navy')
                                        ->required(),
                                ]),
                            Section::make('Kop Surat Dokumen')
                                ->description('Dipakai pada Berita Acara dan dokumen cetak lainnya.')
                                ->schema([
                                    Toggle::make('use_letterhead_image')
                                        ->label('Gunakan gambar kop surat')
                                        ->helperText('Nonaktif berarti kop dirakit otomatis dari logo dan data instansi.')
                                        ->live(),

                                    FileUpload::make('letterhead')
                                        ->label('Gambar Kop Surat')
                                        ->helperText('Lebar ideal 1700 px (setara 170 mm pada 254 dpi). Format PNG atau JPG.')
                                        ->image()
                                        ->disk('public')
                                        ->directory('branding')
                                        ->visibility('public')
                                        ->maxSize(2048)
                                        ->visible(fn ($get) => $get('use_letterhead_image')),
                                ]),
                            Section::make('Logo')
                                ->description('Format PNG latar transparan. Maksimal 1 MB.')
                                ->schema([
                                    Grid::make(2)->schema([
                                        FileUpload::make('logo_light')
                                            ->label('Logo (tema terang)')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->imageEditor(),

                                        FileUpload::make('logo_dark')
                                            ->label('Logo (tema gelap)')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->maxSize(1024)
                                            ->imageEditor(),
                                    ]),

                                    Grid::make(2)->schema([
                                        FileUpload::make('favicon')
                                            ->label('Favicon')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->maxSize(512),

                                        FileUpload::make('label_logo')
                                            ->label('Logo untuk Stiker Aset')
                                            ->helperText('Versi kecil dan sederhana. Detail halus tidak terbaca di ukuran 60×40 mm.')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->maxSize(512),
                                    ]),
                                ]),

                            Section::make('Warna Kustom')
                                ->description('Timpa warna preset dengan warna pilihan sendiri.')
                                ->schema([
                                    Toggle::make('use_custom_colors')
                                        ->label('Gunakan warna kustom')
                                        ->live(),

                                    Grid::make(2)
                                        ->visible(fn ($get) => $get('use_custom_colors'))
                                        ->schema([
                                            ColorPicker::make('primary_color')
                                                ->label('Warna Utama')
                                                ->hex(),

                                            ColorPicker::make('accent_color')
                                                ->label('Warna Aksen')
                                                ->hex(),
                                        ]),
                                ]),
                        ]),

                    /* ------------------------- Pejabat --------------------------- */
                    Tabs\Tab::make('Pejabat')
                        ->icon(Heroicon::OutlinedUserGroup)
                        ->schema([
                            Section::make('Penanda Tangan Dokumen')
                                ->description('Nama yang tercetak di Berita Acara dan BAST.')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('director_name')->label('Direktur')->maxLength(120),
                                        TextInput::make('director_nip')->label('NIP/NIDN Direktur')->maxLength(30),

                                        TextInput::make('vice_director_name')->label('Wakil Direktur')->maxLength(120),
                                        TextInput::make('vice_director_nip')->label('NIP/NIDN Wadir')->maxLength(30),

                                        TextInput::make('asset_head_name')->label('Ka. Aset & Sarpras')->maxLength(120),
                                        TextInput::make('asset_head_nip')->label('NIP/NIDN Ka. Sarpras')->maxLength(30),
                                    ]),
                                ]),
                        ]),

                    /* ------------------------- Kode & QR ------------------------- */
                    Tabs\Tab::make('Kode & QR')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->schema([
                            TextInput::make('asset_code_prefix')
                                ->label('Prefix Kode Aset')
                                ->helperText('Contoh: PA menghasilkan kode PA-26-LAP-0001. Hanya berlaku untuk aset baru — kode lama tidak berubah.')
                                ->required()
                                ->maxLength(5)
                                ->alpha()
                                ->extraInputAttributes(['style' => 'text-transform:uppercase']),

                            Toggle::make('public_qr_enabled')
                                ->label('Aktifkan halaman publik hasil scan QR')
                                ->helperText('Nonaktif berarti QR hanya bisa dibuka oleh pengguna yang sudah login.'),

                            Toggle::make('public_damage_report_enabled')
                                ->label('Izinkan lapor kerusakan tanpa login')
                                ->helperText('Memudahkan pelaporan, tapi berpotensi menerima laporan iseng. Bisa dimatikan sewaktu-waktu.'),
                        ]),
                ]),
        ]);
    }

    protected function afterSave(): void
    {
        Sapra::flush();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
