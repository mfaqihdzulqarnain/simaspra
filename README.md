<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# PETUNJUK TEKNIS
## SIMASPRA — Sistem Manajemen Aset dan Sarana Prasarana
### RUANG LINGKUP UNIVERSITAS

Dokumen ini ditujukan bagi pengembang, administrator sistem, dan bagian IT yang akan memelihara atau mengembangkan SIMASPRA.

---

## Daftar Isi

1. Ikhtisar Sistem
2. Spesifikasi Teknis
3. Instalasi dari Nol
4. Struktur Direktori
5. Struktur Database
6. Enum dan Mesin Status
7. Hak Akses dan Pembatasan Data
8. Lapisan Layanan
9. Penomoran Dokumen
10. Kode QR dan Label
11. Modul Laboratorium
12. Modul Reservasi Ruang dan Sewa
13. Pengaturan Sistem
14. Notifikasi
15. Tugas Terjadwal
16. Backup dan Pemulihan
17. Keamanan
18. Kinerja
19. Pemeliharaan Rutin
20. Penanganan Masalah
21. Pengembangan Lanjutan
22. Lampiran

---

## 1. Ikhtisar Sistem

### Arsitektur

Aplikasi Laravel monolitik dengan dua antarmuka:

```
┌─────────────────────────────────────────────────┐
│  Panel Admin  /asetadmin                        │
│  Filament + Livewire                            │
│  Seluruh operasional, 21 Resource, 6 Page       │
│  Navigasi atas (topNavigation)                  │
├─────────────────────────────────────────────────┤
│  Halaman Publik  /                              │
│  Blade + Tailwind, dirender server              │
│  Beranda · Cek Aset · Scan QR · Halaman Ruang   │
│  Cetak PDF                                      │
├─────────────────────────────────────────────────┤
│  Lapisan Layanan  app/Services                  │
│  Logika bisnis, transaksi database              │
├─────────────────────────────────────────────────┤
│  Model + Observer  app/Models, app/Observers     │
│  Relasi, cast enum, efek samping otomatis        │
├─────────────────────────────────────────────────┤
│  MySQL 8                                        │
└─────────────────────────────────────────────────┘
```

### Prinsip desain

**Transaksi, bukan edit.** Data turunan seperti lokasi dan status aset tidak diubah langsung. Perubahan terjadi sebagai efek samping dari pencatatan transaksi, ditangani Observer.

Contoh: `AssetMovement::create()` memicu `booted()` yang memperbarui `unit_id` dan `room_id` pada aset. Tidak ada satu pun tempat di kode yang mengubah lokasi aset secara langsung.

**Tidak ada penghapusan permanen.** Model transaksional memakai `SoftDeletes`. Aset yang dihapuskan berubah status, tidak dibuang.

**Logika di layanan, bukan di antarmuka.** Filament Resource hanya mengumpulkan input dan memanggil layanan. Ini membuat logika bisa diuji dan dipanggil ulang dari tempat lain, misalnya dari perintah artisan.

**Pembatasan data di lapisan model.** Trait `BelongsToUnit` memasang global scope. Pengembang tidak perlu mengingat untuk menyaring per unit di setiap query.

---

## 2. Spesifikasi Teknis

### Perangkat lunak

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.3 atau 8.4 | Ekstensi: mbstring, pdo_mysql, gd, zip, fileinfo. Terpasang: 8.4 |
| Laravel | 13 | Terpasang: 13.26 |
| Filament | 5.x | Panel `asetadmin`. Terpasang: 5.7 |
| Livewire | 4 | Mengikuti versi Filament. Terpasang: 4.4 |
| MySQL | 8.0+ | Atau MariaDB 10.11+ |
| Node.js | 20+ | Untuk build aset front-end |
| Tailwind CSS | 4 | Build lewat Vite |

### Paket utama

| Paket | Fungsi |
|---|---|
| `bezhansalleh/filament-shield` | Pembangkitan policy dan permission |
| `spatie/laravel-permission` | Peran dan hak akses |
| `spatie/laravel-medialibrary` | Foto dan dokumen |
| `spatie/laravel-activitylog` | Jejak audit |
| `spatie/laravel-settings` | Pengaturan sistem |
| `spatie/laravel-backup` | Backup terjadwal |
| `simplesoftwareio/simple-qrcode` | Pembangkitan QR |
| `barryvdh/laravel-dompdf` | Berita acara dan label |
| `maatwebsite/excel` | Impor dan ekspor |
| `joaopaulolndev/filament-pdf-viewer` | Pratinjau PDF di panel |

### Catatan versi paket

Beberapa paket Spatie mengubah namespace antar versi mayor. Yang perlu diperhatikan bila melakukan pembaruan:

```php
// Versi yang dipakai saat ini
use Spatie\Activitylog\Models\Concerns\LogsActivity;   // bukan \Traits\
use Spatie\Activitylog\Support\LogOptions;             // bukan \LogOptions

// Nama method yang berubah
->dontLogEmptyChanges()   // dulu ->dontSubmitEmptyLogs()
```

Sebelum menaikkan versi paket, periksa keberadaan class dan method:

```bash
php -r "require 'vendor/autoload.php'; var_dump(trait_exists('Spatie\Activitylog\Models\Concerns\LogsActivity'));"
```

---

## 3. Instalasi dari Nol

### Persiapan

```bash
git clone <repositori> simaspra
cd simaspra
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Konfigurasi `.env`

```env
APP_NAME="SIMASPRA"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://simaspra.kampus.ac.id
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simaspra
DB_USERNAME=simaspra_user
DB_PASSWORD=<kata-sandi-kuat>

FILESYSTEM_DISK=public
MEDIA_DISK=public

LOG_DEPRECATIONS_CHANNEL=null

# Jalur folder mysqldump untuk backup
DB_DUMP_PATH=/usr/bin
```

### Basis data

```sql
CREATE DATABASE simaspra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'simaspra_user'@'localhost' IDENTIFIED BY '<kata-sandi-kuat>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
  ON simaspra.* TO 'simaspra_user'@'localhost';
FLUSH PRIVILEGES;
```

Hak `DROP` dan `GRANT` sengaja tidak diberikan. Untuk menjalankan migrasi yang menghapus tabel, gunakan akun terpisah.

### Migrasi dan data awal

```bash
php artisan migrate --force
php artisan storage:link

php artisan shield:generate --all --panel=asetadmin
php artisan db:seed --class=RolePermissionSeeder
php artisan shield:super-admin
```

`shield:generate` membuat policy serta izin halaman dan widget. `RolePermissionSeeder` membuat sendiri izin model (`Aksi:Model`) yang belum ada untuk setiap model di daftarnya, lalu menetapkan matriks hak akses seluruh peran. Lihat bab 7.

### Build front-end

```bash
npm run build
```

### Optimasi produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Jalankan `php artisan optimize:clear` setiap kali mengubah konfigurasi atau kode.

### Cron

```
* * * * * cd /var/www/simaspra && php artisan schedule:run >> /dev/null 2>&1
```

### Izin folder

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Server kampus berbasis Windows

Produksi saat ini berjalan di PC kampus yang difungsikan sebagai server, dengan Laravel Herd (PHP dan Nginx), DBngin (MySQL), dan phpMyAdmin bawaan Herd. Situs dipetakan ke subdomain di DNS domain kampus. Yang perlu dijaga pada susunan ini:

- **Scheduler** dijalankan Windows Task Scheduler setiap menit dengan `php artisan schedule:run`. Atur tugas itu ke **Run whether user is logged on or not**, supaya tetap berjalan tanpa ada yang login
- **Herd dan DBngin** diatur menyala otomatis saat Windows mulai. Tanpa itu, sistem mati setiap kali PC restart atau listrik padam
- **Backup** wajib disalin ke luar PC, misalnya Google Drive atau NAS. Backup yang disimpan di PC yang sama ikut hilang bila diska rusak
- `DB_DUMP_PATH` menunjuk ke folder `bin` MySQL milik DBngin. Lihat bab 16

---

## 4. Struktur Direktori

```
app/
├── Enums/                   27 enum + Concerns/EnumHelpers.php
├── Exports/                 AssetTemplateExport, UserTemplateExport
├── Filament/
│   ├── Concerns/            RedirectsToIndex
│   ├── Pages/               Dashboard, BerandaSaya, BerandaLaboran,
│   │                        ManageGeneralSettings, PindaiSerahTerima,
│   │                        RekapLaboratorium, JadwalRuang
│   ├── Resources/           21 Resource
│   └── Widgets/             5 widget dashboard
├── Http/Controllers/        Home, PublicAsset, PublicRoom, Label, Handover,
│                            Opname, Kir, RentalReceipt, RekapLab
├── Imports/                 AssetImport, UserImport
├── Models/
│   ├── Concerns/            BelongsToUnit, HasPublicCode
│   ├── Scopes/              LoanVisibilityScope, RoomBookingVisibilityScope,
│   │                        HandoverVisibilityScope
│   └── (32 model + User)
├── Observers/               Asset, WorkOrder, Loan, Disposal, DamageReport
├── Services/                Logika bisnis
├── Settings/                GeneralSettings
└── Support/                 Sapra, Phone, Terbilang

config/sapra.php             Konfigurasi khusus aplikasi
database/settings/           Migrasi pengaturan
database/seeders/            RolePermissionSeeder, RoomBookingPermissionSeeder,
                             RentalDecreePermissionSeeder
resources/views/
├── partials/                aset-grafis, panel-grafis (grafis halaman publik)
├── filament/auth/           login-aside (panel kiri halaman masuk)
├── filament/pages/          Halaman kustom panel
├── public/                  home, check, asset, room
└── print/                   bast, kir, label, label-sheet, opname,
                             room-qr, sewa, rekap-lab
```

### Konvensi penamaan

- Nama kelas, method, dan variabel dalam bahasa Inggris
- Label antarmuka, pesan, dan komentar dalam bahasa Indonesia
- Enum memakai PascalCase untuk case, snake_case untuk nilai

---

## 5. Struktur Database

32 tabel aplikasi, di luar tabel bawaan Laravel dan paket.

### Data master

**`units`** — unit, prodi, lembaga, bagian
`id`, `code` unik, `name`, `type`, `parent_id`, `head_user_id`, `phone`, `email`, `is_active`

**`buildings`** — gedung
`id`, `code` unik, `name`, `address`, `floor_count`, `is_active`

**`rooms`** — ruangan
`id`, `public_code` unik, `building_id`, `unit_id`, `laboratory_id`, `code` unik, `name`, `floor`, `area_m2`, `capacity`, `pic_user_id`, `is_active`, `is_bookable`, `booking_types` (JSON), `booking_setup_minutes`, `booking_cleanup_minutes`

Kolom reservasi dijelaskan di bab 12.

**`asset_categories`** — kategori aset, hierarkis
`id`, `parent_id`, `code` unik, `name`, `useful_life_years`, `residual_percent`, `requires_calibration`, `calibration_interval_months`, `is_active`

**`vendors`**, **`contracts`** — penyedia dan kontrak

**`number_sequences`** — deret nomor dokumen
`id`, `type`, `year`, `last_number` · unik gabungan `(type, year)`

### Aset

**`assets`** — tabel inti

| Kolom | Keterangan |
|---|---|
| `id` | Primary key internal, tidak pernah keluar ke URL |
| `uuid` | Cadangan untuk integrasi API |
| `public_code` | 8 karakter acak, dipakai di URL dan QR |
| `code` | Kode terbaca manusia, `PA-26-LAP-0001` |
| `quantity` | Jumlah unit. 1 = aset tunggal, lebih dari 1 = aset kolektif (satu kode untuk banyak unit) |
| `asset_category_id`, `unit_id`, `room_id` | Relasi wajib dan lokasi |
| `vendor_id`, `contract_id`, `procurement_request_item_id` | Asal-usul |
| `year_acquired`, `acquisition_date`, `acquisition_source`, `acquisition_value` | Perolehan. `acquisition_value` selalu **nilai total**; nilai per unit dihitung `unitValue()` |
| `condition`, `status` | Kondisi fisik dan status transaksional |
| `warranty_until`, `calibration_due_at` | Tenggat |
| `is_loanable`, `loanable_by` | Boleh dipinjam, dan oleh siapa (lihat bab 11) |
| `label_printed_at`, `label_print_count` | Pelacakan cetak stiker |

Soft delete aktif. Indeks pada `status`, `condition`, `unit_id`, `room_id`, `asset_category_id`, `year_acquired`.

**Aset kolektif.** `Asset::isCollective()` bernilai benar bila `quantity > 1`. Dampaknya:

| Bagian | Aset tunggal | Aset kolektif |
|---|---|---|
| Peminjaman | Satu baris `loan_items` = satu unit | `loan_items.quantity` berisi jumlah unit; ketersediaan dihitung dari sisa unit |
| Status saat dipinjam | Menjadi `Dipinjam` | Tidak berubah; sisa unit tetap bisa dipinjam |
| Pengembalian | `condition_after` | `returned_quantity` dan `damaged_quantity`; kondisi aset tidak diubah |
| Perintah kerja | Mengubah status dan kondisi aset | Tidak mengubah status dan kondisi; unit hilang dikurangi lewat `MaintenanceService::fail()` |
| Stock opname | Ditemukan atau tidak | `expected_quantity` dan `found_quantity`, status `BedaJumlah` |
| Berita acara | `quantity` 1 | `handover_items.quantity` sesuai jumlah; nilai = `unitValue() × quantity` |

**`asset_movements`** — riwayat mutasi
Menyimpan asal dan tujuan unit serta ruang. Model ini yang memperbarui posisi aset lewat `booted()`.

### Transaksi

**`procurement_requests`** + **`procurement_request_items`** — pengajuan
`request_number` nullable, diisi saat diajukan agar draf yang dibatalkan tidak memakan nomor.

**`approvals`** — polimorfik, dipakai seluruh alur persetujuan
`approvable_type`, `approvable_id`, `step`, `role_name`, `user_id`, `action`, `note`, `acted_at`

Satu tabel untuk pengajuan, penghapusan, peminjaman, dan reservasi ruang. Menambah alur baru tidak perlu tabel baru.

**`handovers`** + **`handover_items`** — berita acara
Referensi ke dokumen asal memakai `nullableMorphs('reference')`. Ini yang memungkinkan tabel dibuat sebelum `loans` dan `disposals` ada.

**`loans`** + **`loan_items`** — peminjaman
Indeks `(start_at, end_at)` dipakai untuk deteksi bentrok jadwal. `loan_items` menyimpan `quantity` (default 1), serta `returned_quantity` dan `damaged_quantity` untuk pengembalian aset kolektif.

**`loan_consumable_items`** — alat massal dalam peminjaman
`loan_id`, `consumable_id`, `quantity`, `returned_quantity`, `damaged_quantity`, `note`. Baris tidak dihapus saat peminjaman ditolak atau dibatalkan, sehingga riwayat permintaannya tetap terlihat.

**`disposals`** + **`disposal_items`** — penghapusan

### Pemeliharaan

**`maintenance_schedules`** — jadwal preventif dan kalibrasi
Berlaku untuk satu aset (`asset_id`) atau satu kategori (`asset_category_id`). Isi salah satu, tidak keduanya. Aturan ini ditegakkan di lapisan aplikasi, bukan database.

**`damage_reports`** — laporan kerusakan
`reported_by` nullable karena laporan bisa masuk tanpa login lewat scan QR. `ip_address` disimpan untuk pembatasan laju.

`asset_id` nullable sejak laporan bisa berasal dari alat massal. Kolom `consumable_id` dan `quantity` mencatat alat massal yang rusak atau hilang, dan `loan_id` menautkan laporan ke peminjaman yang mengungkapnya. Aksesor `item_label` menampilkan kode aset atau nama alat massal beserta jumlahnya; `laboratoryId()` mencari lab terkait dari pinjaman, alat massal, atau ruang aset.

**`work_orders`** — perintah kerja

### Inventarisasi dan lainnya

**`stock_opnames`** + **`stock_opname_items`**
`asset_id` nullable dan `unregistered_name` menangani barang yang ditemukan tapi belum tercatat. Untuk aset kolektif: `expected_quantity` (jumlah tercatat saat opname dibuka), `found_quantity`, dan `quantity_follow_up` (`sesuaikan` atau `laporkan`).

**`consumables`** + **`consumable_movements`**
`current_stock` adalah nilai turunan yang disimpan agar tidak perlu SUM tiap tampil. `stock_after` menyimpan sisa setelah tiap transaksi, memungkinkan kartu stok dicetak. `type` membedakan `habis_pakai` dan `alat_massal`; `on_loan` menyimpan jumlah alat massal yang sedang dipinjam; `laboratory_id` menautkan barang ke lab, baik habis pakai maupun alat massal. `unit_id` adalah unit pemilik; migrasinya mengisi data lama dari unit laboratoriumnya. Stok tersedia = `current_stock − on_loan`.

**`depreciations`** — penyusutan
Tabel dan logikanya sudah ada, tapi belum ada antarmuka. Lihat bab 21.

### Laboratorium

**`laboratories`** — satuan pengelola alat dengan aturannya sendiri
**`laboratory_user`** — pivot penugasan laboran, unik gabungan
**`loan_reminders`** — log pengingat WhatsApp

Rinciannya di bab 11.

### Reservasi dan sewa

**`room_bookings`** — reservasi ruang, bertautan ke `loans` lewat `loan_id` untuk pelengkap
**`rental_decrees`** — SK tarif sewa, berkas PDF lewat MediaLibrary
**`rental_rates`** — tarif per ruang di dalam SK

Rinciannya di bab 12.

### Diagram relasi ringkas

```
Unit ──┬── Asset ──┬── AssetMovement
       │           ├── DamageReport ── WorkOrder
       │           ├── LoanItem ── Loan
       │           ├── DisposalItem ── Disposal
       │           ├── HandoverItem ── Handover
       │           └── StockOpnameItem ── StockOpname
       │
       ├── ProcurementRequest ── ProcurementRequestItem
       ├── Disposal
       ├── Laboratory
       └── User

Building ── Room ──┬── Asset
                   ├── Laboratory (belongsTo)
                   └── RoomBooking ──┬── Loan (pelengkap, belongsTo)
                                     └── RentalDecree ── RentalRate ── Room

Laboratory ─┬── Room (hasMany) ── Asset (hasManyThrough)
            ├── User (belongsToMany, pivot laboratory_user)
            └── Loan (hasMany)

Loan ──┬── LoanItem ── Asset
       ├── LoanConsumableItem ── Consumable
       ├── LoanReminder
       ├── Laboratory
       └── User (peminjam, penyetuju, dosen penanggung jawab)

AssetCategory ── Asset
Vendor ── Contract ── Asset

Approval (polimorfik) → ProcurementRequest | Disposal | Loan | RoomBooking
Handover (polimorfik) → ProcurementRequest | Disposal | Loan
```

---

## 6. Enum dan Mesin Status

27 enum di `app/Enums`. Seluruhnya berbasis string dan mengimplementasikan `HasLabel` dari Filament; hampir semuanya juga `HasColor`. Label Indonesia dan warna badge muncul otomatis di seluruh antarmuka.

Enum yang ditambahkan untuk aset kolektif, reservasi, dan sewa:

| Enum | Nilai |
|---|---|
| `BookingStatus` | `diajukan`, `disetujui`, `ditolak`, `batal`. `occupiesRoom()` benar untuk dua yang pertama |
| `BookingType` | `perkuliahan`, `rapat`, `acara` |
| `RateUnit` | `hari`, `sesi` |
| `PaymentStatus` | `belum_bayar`, `menunggu_verifikasi`, `lunas` |
| `QuantityFollowUp` | `sesuaikan`, `laporkan` |
| `ConsumableType` | `habis_pakai`, `alat_massal`. `isLoanable()` benar untuk alat massal |

`OpnameItemStatus` mendapat nilai baru `beda_jumlah`.

### Alasan memakai enum, bukan tabel

Status menentukan perilaku sistem. `WorkOrderType::Kalibrasi` memicu pembaruan `calibration_due_at` di Observer. `AssetStatus::Aktif` menentukan apakah aset boleh dipinjam.

Bila status bisa ditambah bebas lewat panel, perilaku itu tidak bisa dijamin. Menambah status berarti menambah kode, dan itu memang seharusnya lewat pengembangan, bukan konfigurasi.

### Menambah nilai enum

```php
// app/Enums/WorkOrderType.php
case Instalasi = 'instalasi';
```

Lalu tambahkan di `getLabel()` dan `getColor()`. Kolom database sudah bertipe string, tidak perlu migrasi.

Periksa juga seluruh `match` yang menangani enum tersebut. PHP akan melempar `UnhandledMatchError` bila ada case yang terlewat.

### Mesin status utama

**Pengajuan** (`RequestStatus`)
```
Draf → Diajukan → VerifikasiSarpras → MenungguWadir → Disetujui → Disalurkan → Selesai
                        ↓                    ↓
                     Revisi               Ditolak
```

Method `actorRole()` mengembalikan peran yang harus bertindak pada tiap status. Dipakai untuk badge navigasi dan filter "Menunggu Tindakan Saya".

**Penghapusan** (`DisposalStatus`)
```
Usulan → Pemeriksaan → MenungguWadir → Disetujui → Dieksekusi → Arsip
                ↓             ↓
             Ditolak      Ditolak
```

**Peminjaman** (`LoanStatus`)
```
Diajukan → Disetujui → Dipinjam → Dikembalikan
    ↓                      ↓
 Ditolak              Terlambat
```

**Perintah kerja** (`WorkOrderStatus`)
```
Dilaporkan → Ditugaskan → Dikerjakan → Selesai
                              ↓
                            Gagal
```

Tombol **Tugaskan** tampil pada status Dilaporkan dan Ditugaskan, khusus Kabag Sarpras dan Super Admin. `MaintenanceService::start()` menolak pekerjaan tanpa teknisi maupun vendor.

**Reservasi ruang** (`BookingStatus`)
```
Diajukan → Disetujui ──(waktu lewat)──→ tampil "Selesai"
    ↓          ↓
 Ditolak     Batal
```

"Berlangsung" dan "Selesai" bukan nilai di database, melainkan aksesor `status_label` yang dihitung dari waktu. Tidak perlu scheduler.

**Pembayaran sewa** (`PaymentStatus`)
```
(null) ──disetujui, biaya > 0──→ BelumBayar → MenungguVerifikasi → Lunas
                                     └────────── Tandai Lunas ─────────┘
```

---

## 7. Hak Akses dan Pembatasan Data

Dua mekanisme berlapis dan saling melengkapi.

### Lapisan 1: Permission

Filament Shield membangkitkan policy dan permission untuk setiap Resource. Format nama:

```
{Aksi}:{Model}

Contoh: ViewAny:Asset, Create:Loan, Update:ProcurementRequest
```

**`ViewAny`** menentukan apakah menu muncul di navigasi. **`View`** menentukan akses ke halaman detail.

Dua belas peran: `super_admin`, `pimpinan`, `wadir`, `kabag_sarpras`, `kabag`, `kaprodi`, `staf_ruang`, `teknisi`, `laboran`, `dosen`, `mahasiswa`, `peminjam_eksternal`.

`config/filament-shield.php` memakai `define_via_gate = false`, sehingga `super_admin` tidak mem-bypass lewat Gate. Peran ini mendapat seluruh izin secara eksplisit dari seeder.

Matriks hak akses didefinisikan di `database/seeders/RolePermissionSeeder.php`:

- Sebelum menetapkan hak akses, `pastikanIzinModel()` membuat izin standar (`View`, `ViewAny`, `Create`, `Update`, `Delete`, `DeleteAny`, `Restore`, `RestoreAny`, `ForceDelete`, `ForceDeleteAny`, `Replicate`, `Reorder`) untuk setiap model di properti `$model` yang belum punya. Modul baru cukup didaftarkan di `$model` dan matriksnya.
- Seeder memakai `syncPermissions`. **Izin yang diatur manual lewat menu Peran akan tertimpa.** Jalankan hanya bila matriks di berkas memang menjadi acuan.

Untuk menambah izin satu modul tanpa menyentuh izin lain, tersedia seeder khusus yang hanya menambah (`givePermissionTo`):

```bash
# Reservasi Ruang
php artisan db:seed --class=RoomBookingPermissionSeeder

# Tarif Sewa
php artisan db:seed --class=RentalDecreePermissionSeeder
php artisan permission:cache-reset
```

| Model | Izin per peran |
|---|---|
| `RoomBooking` | `super_admin` dan `kabag_sarpras` semua; peran lain `View`, `ViewAny`, `Create`, `Update` |
| `RentalDecree` | `super_admin` dan `kabag_sarpras` semua; `pimpinan` dan `wadir` `View`, `ViewAny` |

```bash
# Setelah menambah Resource baru
php artisan shield:generate --all --panel=asetadmin
php artisan db:seed --class=RolePermissionSeeder
```

### Lapisan 2: Pembatasan per unit

Trait `App\Models\Concerns\BelongsToUnit` memasang global scope:

```php
static::addGlobalScope('unit', function (Builder $query) {
    $user = Auth::user();

    // Tanpa login: console, seeder, queue
    if (! $user) return;

    // super_admin, pimpinan, wadir, kabag_sarpras
    if ($user->canSeeAllUnits()) return;

    $model = $query->getModel();

    // Model boleh menentukan aturannya sendiri
    if (method_exists($model, 'applyUnitVisibility')
        && $model::applyUnitVisibility($query, $user)) {
        return;
    }

    if (! $user->unit_id) {
        // Punya akses tapi belum ditetapkan unit
        $query->whereRaw('1 = 0');
        return;
    }

    $query->where($model->getTable() . '.unit_id', $user->unit_id);
});
```

Dipakai pada `Asset`, `ProcurementRequest`, dan `Disposal`.

Celah `applyUnitVisibility()` memungkinkan model menyimpang dari penyaringan unit. `Asset` memakainya untuk laboran, yang dibatasi per lab lintas unit, bukan per unit. Lihat bab 11.

**Melewati scope** untuk keperluan laporan lintas unit atau akses publik:

```php
Asset::query()->allUnits()->where(...)->get();
```

Ini yang dipakai pada halaman scan QR publik, agar tamu yang belum login tetap bisa melihat informasi aset.

### Lapisan 2b: Pembatasan peminjaman

`Loan` **tidak** memakai `BelongsToUnit`. Peminjaman bukan milik unit, melainkan milik orang dan lab.

Scope tersendiri di `App\Models\Scopes\LoanVisibilityScope` mengatur siapa melihat pinjaman siapa. Rinciannya di bab 11.

Ini menutup kebocoran yang ada sebelum modul lab dibuat: tanpa scope itu, siapa pun dengan izin `ViewAny:Loan` melihat seluruh peminjaman kampus lengkap dengan nomor HP peminjam.

### Lapisan 2c: Pembatasan lain

| Data | Mekanisme | Aturan |
|---|---|---|
| Berita acara (`Handover`) | Global scope `HandoverVisibilityScope` | Pengguna `canSeeAllUnits()` melihat semua. Laboran: berita acara yang `reference`-nya pinjaman di lab yang diampu. Lainnya: `unit_id` miliknya. Semua pengguna juga melihat yang ia buat, serahkan, atau terima (`created_by`, `from_user_id`, `to_user_id`) |
| Barang & Alat Massal (`Consumable`) | `ConsumableResource::getEloquentQuery()` memanggil `scopeVisibleTo()` | `canSeeAllUnits()` semua; laboran barang di lab-nya; lainnya barang unitnya dan lab di bawah unit itu |

Scope berita acara bersifat global, sehingga route cetak `/cetak/bast/{handover}` ikut terlindungi: berita acara di luar wewenang menghasilkan 404, dan `HandoverController` tidak lagi memeriksa `unit_id`.

Pembatasan barang sengaja dipasang di Resource, **bukan** global scope. Formulir peminjaman dan `ConsumableLoanService` harus tetap melihat alat massal lab lain, supaya mahasiswa dari unit berbeda masih bisa meminjamnya.

### Lapisan 3: Pemeriksaan peran di kode

Tombol yang kritis diperiksa ulang secara eksplisit:

```php
->visible(fn (ProcurementRequest $record) =>
    $record->status === RequestStatus::MenungguWadir
    && Auth::user()?->hasAnyRole(['wadir', 'super_admin']))
```

Permission `Update` saja tidak cukup, karena peran lain juga membutuhkannya untuk keperluan berbeda.

Persetujuan peminjaman memakai `LoanService::canDecide($loan)`: Kabag Sarpras dan Super Admin untuk semua, laboran hanya untuk `laboratory_id` yang ada di `laboratoryIds()`-nya, dan tidak untuk pelengkap reservasi. `Approval::record()` mencatat peran pemutus lewat `deciderRole()`.

Pola yang sama dipakai reservasi: `RoomBookingPolicy::update()` memeriksa izin **dan** `RoomBookingService::canEdit()` (hanya pemesan atau penyetuju, hanya saat Diajukan). Persetujuan tidak memakai izin sama sekali, melainkan `RoomBookingService::canApprove()` berdasarkan peran dan penanggung jawab ruang. Lihat bab 12.

### Gerbang akses panel

```php
// app/Models/User.php
public function canAccessPanel(Panel $panel): bool
{
    return $this->is_active && $this->roles()->exists();
}
```

Akun tanpa peran atau nonaktif tidak bisa masuk.

Panel tidak memakai `->registration()`, hanya `->passwordReset()`. Seluruh akun dibuat lewat `UserResource` atau `UserImport`. Isian `roles` di `UserForm` bersifat `required()`, dan impor Excel memvalidasi kolom peran, sehingga tidak ada akun tanpa peran yang perlu "diaktifkan".

### Halaman beranda dan izin halaman

| Halaman | Akses | Catatan |
|---|---|---|
| `Dashboard` | `canAccess()` selalu benar; isi hanya untuk `canView()` | Memakai `HasPageShield` dengan alias `shieldCanAccess`. Tanpa izin *View:Dashboard*, `mount()` mengalihkan ke `Filament::getHomeUrl()` |
| `BerandaLaboran` | Laboran, Kabag Sarpras, Super Admin | |
| `BerandaSaya` | Semua pengguna yang login | Menu hanya tampil bila pengguna tidak punya kedua beranda lain. Seluruh query disaring `user_id`, `created_by`, atau `assigned_to` |
| `ManageGeneralSettings` | Izin *View:ManageGeneralSettings* | `HasPageShield` |

`Dashboard` adalah rute utama panel (`/asetadmin`), jadi tidak diberi 403, melainkan dialihkan. Beranda tujuan ditentukan `homeUrl` panel:

```php
->homeUrl(fn (): string => match (true) {
    Dashboard::canView() => Dashboard::getUrl(),
    BerandaLaboran::canAccess() => BerandaLaboran::getUrl(),
    default => BerandaSaya::getUrl(),
})
```

Gunakan `Dashboard::canView()`, bukan `canAccess()`. `canAccess()` selalu benar, sehingga memakainya di sini membuat pengalihan berputar tanpa henti.

Tombol **Kembali ke Beranda** di `resources/views/errors/403.blade.php` memakai `Filament::getPanel('asetadmin')->getHomeUrl()`, sehingga mengarah ke beranda yang sama.

Izin halaman dibuat tanpa menyentuh policy:

```bash
php artisan shield:generate --page=Dashboard,ManageGeneralSettings \
    --option=permissions --panel=asetadmin
```

### Konfirmasi kata sandi untuk penghapusan berita acara

Bulk action **Hapus Berita Acara** di `HandoversTable` meminta kata sandi akun yang sedang login lewat aturan `current_password`. Nama dan email akun ditampilkan hanya-baca. Isian itu memakai `dehydrated(false)`, sehingga kata sandi hanya dipakai untuk validasi dan tidak tersimpan. Model `Handover` menghapus `items()` di event `deleting`.

---

## 8. Lapisan Layanan

Logika bisnis berada di `app/Services`. Resource Filament hanya mengumpulkan input dan memanggil layanan.

| Layanan | Tanggung jawab |
|---|---|
| `ProcurementService` | Alur pengajuan: submit, verify, approve, reject, requestRevision |
| `DistributionService` | Penyaluran: membuat aset dari item pengajuan, menerbitkan BAST |
| `LoanService` | Peminjaman: ketersediaan per unit, syncItems, approve, reject, handOut, handBack, createAndHandOut, markOverdue |
| `ConsumableLoanService` | Alat massal dalam peminjaman: available, check, sync, handBack, release |
| `MaintenanceService` | Pemeliharaan: createFromReport, assign, start, finish, fail, generateDueWorkOrders |
| `DisposalService` | Penghapusan: submit, inspect, approve, execute, archive |
| `MutationService` | Mutasi aset, tunggal maupun massal |
| `OpnameService` | Stock opname: start, scan, close, report |
| `ReminderService` | Pengingat WhatsApp: susun pesan, tautan wa.me, log |
| `ScanService` | Pencarian aset dan pinjaman aktifnya dari kode QR |
| `LabStatsService` | Agregasi rekap peminjaman laboratorium |
| `LabelService` | Pembangkitan PDF label |
| `QrService` | Pembangkitan QR SVG |
| `NotifyService` | Pengiriman notifikasi database |
| `StatsService` | Agregasi untuk dashboard dan halaman publik: publicSummary, labSummary, publicActivity |
| `RoomBookingService` | Reservasi: cek bentrok, validasi, persetujuan, pelengkap, biaya, pembayaran, jadwal publik |
| `RentalFeeService` | Perhitungan biaya sewa dari SK tarif yang berlaku |

### Pola yang dipakai

**Transaksi database membungkus operasi majemuk.**

```php
return DB::transaction(function () use ($request, $rows) {
    // membuat aset, membuat handover, memperbarui status
});
```

**Notifikasi dikirim di luar transaksi.**

Bila transaksi gagal dan dibatalkan, notifikasi tidak boleh terlanjur terkirim.

```php
DB::transaction(fn () => /* ... */);

NotifyService::toRoles(['kabag_sarpras'], 'Pengajuan baru', ...);
```

**Perubahan status memicu efek samping lewat Observer.**

Layanan hanya mengubah status. Observer yang menangani sisanya.

```php
// LoanService
$loan->forceFill(['status' => LoanStatus::Dipinjam])->save();

// LoanObserver menangkap dan mengubah status seluruh aset terkait
```

Ini menjaga agar perubahan status dari mana pun asalnya selalu menghasilkan efek yang sama.

### Dashboard

Beranda utama memakai `AccountWidget` bawaan dan lima widget di `app/Filament/Widgets`, seluruhnya membaca data dari `StatsService`:

| Urutan | Widget | Lebar | Isi |
|---|---|---|---|
| 1 | `StatsOverview` | penuh | Kartu ringkas: Nilai Aset, Aset Aktif, Menunggu Persetujuan, Perintah Kerja Terbuka, Peminjaman Terlambat |
| 2 | `RequestTrendChart` | 1 kolom | Pengajuan diajukan dan disetujui, dengan filter periode |
| 3 | `AssetIntakeChart` | 1 kolom | Aset baru tanpa pengajuan: unit tercatat dan jumlah kode |
| 4 | `ConditionChart` | 1 kolom | Komposisi kondisi aset |
| 5 | `UnitDistributionChart` | 1 kolom | Sebaran aset per unit |

Dashboard berkolom 2, sehingga widget 1 kolom tampil berdampingan; di layar sempit semuanya menjadi satu kolom.

**Tren aset baru.** `AssetIntakeChart` mewarisi `RequestTrendChart` agar filter, gaya, dan opsi grafiknya sama. Karena itu `seri()` dan `transparan()` di kelas induk bersifat `protected`. Datanya dari `StatsService::assetIntakeTrend($months)`: aset `allUnits()` dengan `procurement_request_item_id` kosong, dikelompokkan per bulan `created_at`, berisi `unit` (`SUM(quantity)`) dan `aset` (`COUNT(*)`).

**Tren pengajuan.** `StatsService::requestTrend(int $months = 12)` mengembalikan `labels`, `diajukan`, dan `disetujui` untuk sejumlah bulan terakhir. Disetujui menghitung status Disetujui, Disalurkan, dan Selesai.

`RequestTrendChart` membaca filter `3`, `6`, atau `12` dari properti `$filter`, lalu meneruskannya ke `requestTrend()`. Deskripsi widget ikut berubah lewat `getDescription()`.

Deret bulan disusun dari awal bulan berjalan dengan `subMonthsNoOverflow()`:

```php
$awal = now()->startOfMonth();

$bulan = collect(range($months - 1, 0))
    ->map(fn (int $i) => $awal->copy()->subMonthsNoOverflow($i));
```

`now()->subMonths($i)` tidak dipakai karena pada tanggal 29 sampai 31 hasilnya meluap ke bulan berikutnya. Contohnya 31 Maret dikurangi satu bulan menjadi 3 Maret, sehingga label bulan tampil ganda dan Februari hilang.

Pengaturan tampilan grafik:

- `cubicInterpolationMode: monotone` agar kurva halus tanpa melengkung ke bawah nol
- Area di bawah garis diisi warna garis dengan transparansi 10%
- `interaction.mode: index` agar tooltip menampilkan kedua seri sekaligus
- `scales.y.grace: 15%` agar titik tertinggi tidak menempel di tepi atas
- `$pollingInterval = null`, karena data bulanan tidak perlu diperbarui tiap 5 detik

### Daftar aset

Kolom tabel Resource Aset dirancang hemat tempat:

| Kolom | Implementasi |
|---|---|
| Kondisi / Status | Satu `TextColumn` bernama `kondisi_status`. State berupa array `[condition, status]`, ditampilkan sebagai badge bertumpuk dengan `->badge()->listWithLineBreaks()` |
| Pinjam | `IconColumn::make('is_loanable')->boolean()`, tooltip menampilkan label `loanable_by` |
| Nilai | `acquisition_value` diformat `Sapra::rupiahRingkas()`, tooltip `Sapra::rupiah()` |

Badge kondisi dan status tidak memerlukan pemetaan warna manual. `AssetCondition` dan `AssetStatus` mengimplementasikan `HasLabel` dan `HasColor`, dan Filament membacanya untuk tiap elemen array.

Pengurutan kolom gabungan memakai query eksplisit, karena `kondisi_status` bukan kolom database:

```php
->sortable(query: fn (Builder $query, string $direction) => $query
    ->orderBy('condition', $direction)
    ->orderBy('status', $direction))
```

Filter Kondisi dan Status tetap terpisah, karena keduanya menyaring kolom database, bukan kolom tabel.

Keterangan siapa yang boleh meminjam hanya tampil lengkap di infolist, lewat `TextEntry::make('loanable_by')` dengan placeholder *Tidak dipinjamkan*. Filter Lab dijelaskan di bab 11.

Kolom **Jml** menampilkan `quantity`. Form aset punya isian virtual `nilai_per_unit` (`dehydrated(false)`), terlihat bila `quantity > 1`. Mengubah `quantity` mempertahankan nilai per unit dan menghitung ulang `acquisition_value`; mengubah total menyesuaikan nilai per unit. Yang tersimpan tetap nilai total.

### Peminjaman berbasis jumlah

`Loan::reservedQuantities($start, $end, $exceptLoanId)` mengembalikan `[asset_id => unit terpakai]` dari pinjaman berstatus Diajukan, Disetujui, Dipinjam, dan Terlambat yang beririsan dengan rentang. `conflictingAssetIds()` kini hanya mengembalikan aset yang **seluruh** unitnya terpakai.

`LoanService::availableAssets()` memberi tiap aset atribut sementara `available_quantity`: aset tunggal 1, aset kolektif sisa unitnya. `availableGroups()` menjumlahkan unit per kelompok (kategori, merek, model, ruang), dan `resolveGroupSelection()` mengambil unit berurutan dari aset teratas sampai jumlah terpenuhi. Hasilnya `['items' => [asset_id => jumlah], 'ids' => [...], 'errors' => [...]]`; kunci `ids` dipertahankan untuk pemanggil lama.

Parameter terakhir `bool $outsideLaboratories` pada ketiga method itu menyaring aset yang tidak berada di ruang lab. Dipakai untuk pelengkap reservasi.

`LoanService::syncItems($loan, [asset_id => jumlah])` mengganti seluruh baris aset sebuah pinjaman. Jumlah aset tunggal dipaksa 1, aset kolektif dibatasi `quantity`-nya.

`LoanObserver::markAssetsBorrowed()` melewati aset kolektif, sehingga statusnya tetap Aktif.

`LoanService::reportDamagedReturns()` membuat laporan kerusakan untuk aset tunggal yang kondisinya memburuk, **atau** yang kembali tidak Baik dengan `damage_note` terisi; serta untuk aset kolektif dengan unit rusak atau hilang. Setiap laporan menyimpan `loan_id`.

### Alat massal dalam peminjaman

`ConsumableLoanService` memakai prinsip semua atau tidak sama sekali:

| Method | Perilaku |
|---|---|
| `available($labId, $exceptLoanId)` | Alat massal yang bisa dipilih, dengan atribut `available_for`. Jumlah yang dicadangkan peminjaman yang sedang diedit dihitung sebagai miliknya |
| `check($rows, $exceptLoanId)` | Periksa tanpa menyimpan. Dipanggil sebelum pinjaman dibuat |
| `sync($loan, $rows)` | Kunci baris stok, validasi, lalu ganti cadangan lama dengan yang baru. Bila satu baris gagal, tidak ada yang berubah dan daftar error dikembalikan tanpa melempar exception |
| `handBack($loan, $rows)` | Catat kembali baik dan rusak; sisanya hilang. Rusak dan hilang mengurangi `current_stock` lewat `ConsumableMovement`, dan membuat `DamageReport` dengan `consumable_id`, `quantity`, dan `loan_id` |
| `release($loan)` | Lepas cadangan saat Ditolak atau Batal. Dipanggil `LoanObserver`, baris item tidak dihapus |

Stok alat massal dicadangkan sejak permohonan diajukan dengan menaikkan `on_loan`. Jangan memanggil `sync()` untuk pinjaman yang sudah ditolak atau dibatalkan, karena cadangannya sudah dilepas.

`LoanService::createAndHandOut($data, [asset_id => jumlah], $note, $consumableRows)` membuat pinjaman, baris aset, dan alat massal dalam satu transaksi. Bila alat massal tidak cukup, seluruhnya dibatalkan dan `DomainException` dilempar dengan pesan siap tampil.

### Halaman publik dan halaman masuk

| Berkas | Isi |
|---|---|
| `partials/aset-grafis.blade.php` | Grafis ringkasan SVG: grafik aktivitas 6 bulan, total aset, donat kondisi baik, kartu QR, alat laboratorium. Dipakai halaman depan dan panel grafis |
| `partials/panel-grafis.blade.php` | Panel kiri bertema: logo, judul, teks, grafis. Dipakai halaman masuk dan Cek Aset |
| `filament/auth/login-aside.blade.php` | Disisipkan lewat render hook `SIMPLE_LAYOUT_START` dengan scope `Filament\Auth\Pages\Login`, beserta CSS yang menata ulang `fi-simple-layout` menjadi grid dua kolom |

Data halaman publik hanya agregat yang aman: `publicSummary()` (total aset, persen kondisi baik, jumlah unit), `labSummary()` (alat di ruang lab dan jumlah lab aktif), dan `publicActivity(6)` (aset baru per bulan menurut `acquisition_date`, dan peminjaman yang benar-benar terjadi). Jumlah aset rusak dan laporan kerusakan tidak pernah dikirim ke halaman publik. Seluruhnya di-cache 15 menit.

Warna halaman publik diambil dari `Sapra::theme()` dan dipetakan ke `@theme` Tailwind v4, sehingga pilihan tema di Pengaturan Sistem ikut berlaku.

### Navigasi panel

`AsetadminPanelProvider` memakai `->topNavigation()`. Grup navigasi menjadi menu tarik-turun, dan pada layar sempit Filament otomatis menggantinya dengan laci menu. Tautan Halaman Depan ada di `->userMenuItems()`. Halaman `JadwalRuang` memakai `$shouldRegisterNavigation = false`; menu Reservasi Ruang tetap tersorot lewat `RoomBookingResource::getNavigationItemActiveRoutePattern()`.

---

## 9. Penomoran Dokumen

Tabel `number_sequences` menyimpan deret terpisah per jenis dan tahun.

### Mekanisme

```php
NumberSequence::next(string $type, ?int $year): int
NumberSequence::format(string $type, string $prefix, ?int $year, int $pad): string
```

`next()` mengunci baris dengan `lockForUpdate()` di dalam transaksi. Dua pengguna yang membuat dokumen bersamaan mendapat nomor berbeda.

### Prefiks

| Jenis | Prefiks | Contoh |
|---|---|---|
| Pengajuan | PGJ | `PGJ/2026/00001` |
| Serah terima | BAST | `BAST/2026/00001` |
| Peminjaman | BAPP | `BAPP/2026/00001` |
| Pengembalian | BAPK | `BAPK/2026/00001` |
| Penghapusan barang | BAPB | `BAPB/2026/00001` |
| Mutasi | BAM | `BAM/2026/00001` |
| Perintah kerja | WO | `WO/2026/00001` |
| Opname | OPN | `OPN/2026/00001` |
| Reservasi ruang | RSV | `RSV/2026/00001` |
| Kuitansi sewa | KWT | `KWT/2026/00001` |

Nomor reservasi terbit saat reservasi dibuat (`NumberSequence::format('reservasi', 'RSV')`). Nomor kuitansi terbit sekali saat pembayaran pertama kali dikonfirmasi lunas (`'kuitansi', 'KWT'`). Tagihan sewa tidak punya deret sendiri dan memakai nomor reservasi.

Prefiks berita acara ditentukan oleh `HandoverType::numberPrefix()`, deretnya oleh `HandoverType::sequenceType()`.

### Kode aset

Dibangkitkan `AssetObserver` saat pembuatan:

```
{PREFIKS}-{TAHUN 2 DIGIT}-{KODE KATEGORI}-{URUT 4 DIGIT}

PA-26-LAP-0001
```

Deret terpisah per kategori per tahun. Prefiks diambil dari Pengaturan Sistem dan hanya berlaku untuk aset baru.

### Nomor yang melompat

Nomor tidak dipakai ulang. Dokumen yang dibatalkan menyisakan lubang di deret. Ini perilaku yang benar untuk penomoran resmi.

---

## 10. Kode QR dan Label

### Kode publik

Trait `App\Models\Concerns\HasPublicCode` dipakai `Asset`, `Handover`, `StockOpname`, dan `Room`.

```php
protected const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
protected const CODE_LENGTH = 8;
```

Alfabet Crockford Base32 tanpa I, L, O, dan U untuk menghindari salah baca. Dibangkitkan dengan `random_int()` yang kriptografis, sehingga kode tidak bisa ditebak dari kode lain.

Trait juga menimpa `getRouteKeyName()`, sehingga seluruh route model binding otomatis memakai kode ini, bukan id numerik.

`Room` menimpa aksesor `public_url` dan `qrPayload()` agar QR ruang mengarah ke `route('room.public', $code)`, yaitu `/r/{code}`, bukan ke halaman aset. Papan QR ruang dicetak A5 lewat `PublicRoomController::sign()` dengan template `print/room-qr.blade.php`. Pencetaknya dibatasi untuk pengguna yang melihat semua unit, unit pemilik ruang, atau penanggung jawab ruang.

### Alasan memakai kode pendek, bukan UUID

| Isi QR | Panjang | Versi QR | Ukuran modul pada 22 mm |
|---|---|---|---|
| `.../a/K7M2QX9P` | ~32 karakter | v3 (29×29) | 0,76 mm |
| `.../a/3f7c1e8a-...` | ~55 karakter | v5 (37×37) | 0,59 mm |

Modul lebih besar jauh lebih toleran terhadap tinta meleber, stiker tergores, cahaya redup, dan kamera berkualitas rendah. Untuk stiker kecil, ini menentukan berhasil atau gagalnya pemindaian.

### Spesifikasi label

| Parameter | Nilai |
|---|---|
| Ukuran satuan | 60 × 40 mm |
| Lembar A4 | 2 kolom × 5 baris = 10 label |
| Ukuran QR | 26 mm |
| Error correction | Level Q, 25% |
| Font | DejaVu Sans, 6 pt |

Kertas untuk dompdf ditentukan dalam satuan point:

```php
->setPaper([0, 0, 170.08, 113.39]);   // 60 × 40 mm
```

Konversi: `mm ÷ 25.4 × 72 = point`

### Catatan tentang dompdf

dompdf punya keterbatasan yang memengaruhi cara template ditulis:

- `display: table-cell` pada div tidak diterapkan dengan baik. Gunakan tabel HTML asli.
- `height` pada `td` diabaikan. Terapkan pada div di dalamnya.
- `:last-child` tidak didukung. Gunakan `$loop->last` di Blade.
- Hanya font bawaan seperti DejaVu Sans yang mendukung karakter lengkap.
- Untuk tata letak presisi, posisi absolut lebih dapat diandalkan daripada tabel.

Template lembar A4 memakai posisi absolut dengan koordinat yang dihitung eksplisit, karena tabel tidak konsisten menghasilkan 10 sel dalam satu halaman.

---

## 11. Modul Laboratorium

Modul ini memperluas peminjaman untuk kebutuhan laboratorium, tanpa memisahkan datanya dari sistem aset.

### Model data

```
Laboratory ─┬─ rooms (hasMany)      → Asset (hasManyThrough)
            ├─ staff (belongsToMany lewat laboratory_user)
            ├─ head (belongsTo User)
            └─ loans (hasMany)

Loan ─┬─ laboratory (belongsTo)
      ├─ supervisor (belongsTo User)
      └─ reminders (hasMany LoanReminder)
```

Alat **tidak** ditautkan langsung ke lab. Hubungannya lewat `rooms.laboratory_id`, sehingga memindahkan alat ke ruang lain otomatis memindahkan kepemilikan lab-nya. Tidak ada data yang perlu disinkronkan.

### Tabel baru

**`laboratories`**

| Kolom | Keterangan |
|---|---|
| `code` unik, `name` | Identitas |
| `unit_id`, `head_user_id` | Pengelola dan kepala lab |
| `opens_at`, `closes_at` | Jam operasional, bertipe TIME |
| `student_max_hours` | Batas pinjam mahasiswa, dalam jam |
| `lecturer_max_hours` | Batas pinjam dosen |
| `requires_approval` | Perlu persetujuan laboran |
| `rules` | Tata tertib, ditampilkan ke peminjam |

**`laboratory_user`** — pivot penugasan laboran. Unik gabungan `(laboratory_id, user_id)`. Satu laboran boleh memegang beberapa lab.

**`loan_reminders`** — log pengingat WhatsApp: `loan_id`, `sent_by`, `phone`, `message`, `sent_at`.

### Kolom tambahan

| Tabel | Kolom | Keterangan |
|---|---|---|
| `rooms` | `laboratory_id`, `public_code` | Penautan lab dan kode untuk URL KIR |
| `users` | `nim`, `entry_year` | Identitas mahasiswa dan angkatan |
| `assets` | `loanable_by` | `semua` atau `dosen_saja` |
| `loans` | `laboratory_id` | Lab pengelola, null untuk aset umum |
| `loans` | `borrower_identity` | Salinan NIM atau NIP saat meminjam |
| `loans` | `purpose_type` | Jenis keperluan, enum `LoanPurpose` |
| `loans` | `course_name`, `supervisor_id` | Mata kuliah dan dosen penanggung jawab |

`borrower_identity` sengaja disalin, bukan diambil dari relasi. Bila akun peminjam diubah atau dihapus, catatan peminjaman tetap menunjukkan siapa yang meminjam saat itu.

### Filter Lab pada daftar aset

`Asset` tidak punya relasi `laboratory`. Rantainya `Asset → Room → Laboratory`, dan Laravel tidak menyediakan relasi `belongsTo` bertingkat tanpa paket tambahan. Karena itu `SelectFilter::relationship()` tidak bisa dipakai.

Filter disusun manual dan memakai scope `inLaboratory()`:

```php
SelectFilter::make('laboratory')
    ->label('Lab')
    ->options(fn () => Laboratory::query()
        ->orderBy('code')
        ->pluck('code', 'id')
        ->all())
    ->searchable()
    ->query(fn (Builder $query, array $data) => $query->when(
        $data['value'] ?? null,
        fn (Builder $q, $labId) => $q->inLaboratory((int) $labId),
    )),
```

Nama filter sengaja `laboratory`, bukan `room.laboratory.code`. Filament menyimpan state filter di URL dan session berdasarkan nama itu, dan titik dibaca sebagai path bertingkat.

Bagi laboran, data aset sudah terbatas ke lab-nya lewat scope. Daftar pilihan filter belum, sehingga lab lain tetap muncul tetapi hasilnya kosong. Bila perlu, batasi opsinya dengan `whereIn('id', auth()->user()->laboratoryIds())`.

### Perubahan waktu peminjaman

Kolom `start_date` dan `end_date` diganti menjadi `start_at` dan `end_at` bertipe `DATETIME`. Peminjaman lab dihitung per jam, bukan per hari.

Migrasi konversi menaikkan jam akhir data lama ke `23:59:59`. Tanpa itu, seluruh pinjaman lama langsung dianggap terlambat sejak tengah malam di hari jatuh temponya.

Pengecekan bentrok memakai irisan rentang dengan pembanding ketat:

```php
->where('start_at', '<', $end)
->where('end_at', '>', $start)
```

Pembanding ketat membuat sesi bersambung tidak dianggap bentrok. Alat yang dipinjam 08.00–10.00 tetap bisa dipinjam orang lain mulai 10.00.

Status `Diajukan` ikut dihitung bentrok. Tanpa itu, dua orang bisa mengajukan alat yang sama di jam yang sama, dan bentrokan baru ketahuan saat laboran menyetujui.

### Enum baru

**`LoanPurpose`** — praktikum, perkuliahan, penelitian, tugas_akhir, kegiatan, lainnya.

Dua method menentukan kewajiban pengisian formulir:

```php
public function requiresSupervisor(): bool   // praktikum, penelitian, tugas akhir
public function requiresCourse(): bool       // praktikum, perkuliahan
```

**`LoanableBy`** — semua, dosen_saja. Method `allows(User $user)` memeriksa apakah peminjam berhak.

### Pembatasan data berlapis

Peminjaman memakai scope tersendiri, bukan `BelongsToUnit`.

`app/Models/Scopes/LoanVisibilityScope.php`:

| Peran | Yang terlihat |
|---|---|
| Sarpras, Wadir, Pimpinan, Super Admin | Seluruh pinjaman |
| Laboran | Pinjaman di lab yang diampu, ditambah miliknya sendiri |
| Selain itu | Hanya pinjaman miliknya |

Scope ini **sengaja dilepas** di dua tempat:

```php
Loan::reservedQuantities()    // cek bentrok harus melihat seluruh pinjaman
LoanService::markOverdue()    // scheduler tanpa login
```

Tanpa pelepasan itu, mahasiswa hanya melihat pinjamannya sendiri saat mengecek ketersediaan, dan alat yang sedang dipinjam orang lain akan tampak tersedia.

**Aset** memakai celah di `BelongsToUnit`. Model boleh menentukan aturannya sendiri lewat method statis:

```php
// app/Models/Asset.php
public static function applyUnitVisibility(Builder $query, User $user): bool
{
    if (! $user->hasRole('laboran')) {
        return false;   // pakai penyaringan unit biasa
    }

    $labIds = $user->laboratoryIds();

    if (empty($labIds)) {
        $query->whereRaw('1 = 0');
        return true;
    }

    $query->whereHas('room', fn ($q) => $q->whereIn('laboratory_id', $labIds));

    return true;
}
```

Laboran melihat alat lintas unit selama berada di lab yang diampunya. Ini yang membedakannya dari kaprodi yang dibatasi unit.

### Layanan

| Layanan | Tanggung jawab |
|---|---|
| `ReminderService` | Susun pesan dari templat, buat tautan wa.me, catat log |
| `ScanService` | Cari aset dari kode, temukan pinjaman aktifnya, periksa wewenang |
| `LabStatsService` | Agregasi rekap: ringkasan, alat tersering, peminjam terlambat, tren |

`LoanService` bertambah dua method:

```php
LoanService::maxEnd($start, $borrowerType, $laboratoryId, $borrower): ?Carbon
LoanService::maxDurationLabel($borrowerType, $laboratoryId, $borrower): string
```

Keduanya mengembalikan batas berdasarkan aturan lab bila lab dipilih, atau `config('sapra.loan')` bila tidak.

### Halaman khusus

Ketiganya memakai `canAccess()` dan `shouldRegisterNavigation()` untuk menyembunyikan diri dari peran yang tidak berkepentingan.

**`BerandaLaboran`** — antrean kerja laboran. Mengandalkan `LoanVisibilityScope` untuk penyaringan per lab, sehingga query di dalamnya tidak perlu menyaring ulang.

**`PindaiSerahTerima`** — komponen Livewire dengan properti `kode`, `loanId`, `kondisi`, dan `catatan`. Setelah transaksi, `selesai()` mengosongkan keadaan dan mengirim event `fokus-kode` agar kotak masukan siap untuk pindaian berikutnya.

Tambahan untuk aset kolektif dan alat massal:

| Properti / method | Fungsi |
|---|---|
| `$keranjang[assetId]` | Menyimpan `collective`, `max`, dan `quantity`. Aset kolektif menampilkan isian jumlah |
| `$kolektif` | Hasil pindai aset kolektif: daftar pinjaman berjalan yang memuatnya (dari `ScanService::activeLoansFor()`) dan tombol tambah ke pinjaman baru |
| `$kolektifKembali[itemId]` | Isian kembali baik dan rusak saat pengembalian aset kolektif |
| `$keranjangMassal`, `$massalKembali` | Alat massal dalam pinjaman baru dan pengembaliannya |
| `jumlahDiminta()` | Keranjang menjadi `[asset_id => jumlah]` |
| `bukaPinjaman()`, `prosesPinjaman()` | Menyiapkan tampilan serahkan atau terima untuk satu pinjaman |

`buatPinjaman()` memeriksa sisa unit lewat `Loan::reservedQuantities()` dan stok alat massal lewat `ConsumableLoanService::check()` sebelum menyimpan apa pun. `serahkan()` menolak pelengkap reservasi pihak luar yang belum lunas.

**`RekapLaboratorium`** — penyaring lab dan periode dengan `wire:model.live`. Tombol **Cetak Rekap** (laboran, Kabag Sarpras, Super Admin) membuka `/cetak/rekap-lab?lab=&periode=&bulan=` di tab baru lewat `$this->js('window.open(...)')`.

`RekapLabController::print()` menerima `periode` 1, 3, atau 6 bulan kalender yang berakhir di `bulan` (format `Y-m`). Laboran yang meminta lab di luar `laboratoryIds()`-nya mendapat 403. Data diambil dari `LabStatsService` (`summary`, `topAssets`, `lateBorrowers`, `purposeBreakdown`) dan daftar pinjaman lab itu tanpa `LoanVisibilityScope`. Template `print/rekap-lab.blade.php`, A4 mendatar.

**Alat massal di Pindai.** `alatMassalTampil()` menampilkan 5 alat (`MASSAL_AWAL`) sebelum pencarian dan maksimal 20 saat mencari lewat `$cariMassal`. Alat yang jumlahnya sudah diisi selalu ikut tampil. `mulaiMassal()` membuka mode peminjaman baru tanpa pindaian; section peminjaman baru tampil selama `$aksi === 'baru'`, meski keranjang masih kosong.

### Pengingat WhatsApp

Alurnya:

```
Laboran klik tombol
  → ReminderService::compose() menyusun pesan dari templat
  → ReminderService::log() mencatat ke loan_reminders
  → Browser membuka https://wa.me/62…?text=…
  → Laboran menekan kirim pada WhatsApp
```

**Sistem mencatat bahwa pengingat disiapkan, bukan bahwa pesannya terkirim.** `wa.me` tidak menyediakan umpan balik status pengiriman. Untuk pengiriman otomatis yang terverifikasi, dibutuhkan WhatsApp Business API atau gateway pihak ketiga berbayar.

Nomor HP dinormalkan ke format `62…` lewat `App\Support\Phone`. Mutator pada `User::phone` dan `Loan::borrower_phone` memastikan data masuk selalu seragam, apa pun format aslinya di berkas impor.

### Impor pengguna

`App\Imports\UserImport` memakai email sebagai kunci `updateOrCreate`, sehingga impor ulang aman.

Dua perilaku yang disengaja:

**Kata sandi tidak ditimpa saat pembaruan.** Pengguna yang sudah mengganti kata sandinya tidak kembali ke NIM hanya karena berkasnya diimpor ulang.

**Status aktif tidak disentuh saat pembaruan.** Akun yang sengaja dinonaktifkan tidak hidup kembali. Hanya akun baru yang otomatis aktif.

Peran yang boleh dibuat lewat impor dibatasi di properti `$peranDiizinkan`. Peran istimewa seperti `super_admin` dan `wadir` tidak bisa diberikan lewat berkas Excel.

---

## 12. Modul Reservasi Ruang dan Sewa

Modul ini menangani pemesanan waktu atas ruang, pelengkap yang dipinjam bersamanya, serta tarif dan pembayaran sewa untuk pihak luar.

### Model data

```
Room ─┬─ RoomBooking ─┬─ Loan (pelengkap)
       │               ├─ RentalDecree (dasar biaya)
       │               ├─ Approval
       │               └─ Media: bukti_bayar
       └─ RentalRate ── RentalDecree ── Media: dokumen
```

**Kolom reservasi pada `rooms`**

| Kolom | Keterangan |
|---|---|
| `is_bookable` | Ruang dibuka untuk reservasi |
| `booking_types` | JSON berisi jenis yang diizinkan. Kosong berarti semua |
| `booking_setup_minutes` | Persiapan sebelum acara, dalam menit. Form menampilkan satuan menit, jam, atau hari, maksimal 3 hari |
| `booking_cleanup_minutes` | Beres-beres sesudah acara |

Migrasi `split_booking_buffer` memecah kolom lama `booking_buffer_minutes` menjadi dua kolom di atas, dengan nilai lama disalin ke keduanya.

**`room_bookings`**

| Kolom | Keterangan |
|---|---|
| `booking_number` | `RSV/2026/00001` |
| `room_id`, `loan_id` | Ruang, dan peminjaman pelengkap bila ada |
| `booking_type`, `title`, `description`, `attendees` | Kegiatan |
| `start_at`, `end_at` | Jam acara, **tanpa** persiapan dan beres-beres |
| `borrower_type`, `user_id`, `borrower_*` | Pemesan internal (dari akun) atau pihak luar |
| `status`, `approved_by`, `approved_at`, `rejection_reason`, `cancellation_reason` | Keputusan |
| `fee_amount`, `rental_decree_id`, `fee_breakdown` | Biaya terkunci, SK dasarnya, dan rinciannya (JSON: `hari`, `sk`, `pesan`, `penyesuaian`) |
| `payment_status`, `paid_at`, `payment_verified_by`, `payment_note`, `receipt_number` | Pembayaran dan nomor kuitansi |

**`rental_decrees`**: `number`, `title`, `decree_date`, `effective_from`, `notes`, soft delete, koleksi media `dokumen` (satu PDF).
**`rental_rates`**: `rental_decree_id`, `room_id`, `unit` (`hari`/`sesi`), `name`, `session_hours`, `price`, `notes`.

### Rentang ruang terpakai dan cek bentrok

Setiap reservasi menempati ruang dari `start_at − setup` sampai `end_at + cleanup`. Dua reservasi bentrok bila rentang tempatnya beririsan. Karena jeda sama untuk seluruh reservasi di satu ruang, syaratnya disederhanakan menjadi irisan jam acara yang diperlebar sebesar `setup + cleanup`:

```php
$jeda = $room->setupMinutes() + $room->cleanupMinutes();

RoomBooking::query()
    ->withoutGlobalScope(RoomBookingVisibilityScope::class)
    ->where('room_id', $roomId)
    ->occupying()                  // Diajukan dan Disetujui
    ->overlapping($start->subMinutes($jeda), $end->addMinutes($jeda));
```

Pembanding ketat (`<` dan `>`) membuat sesi bersambung tanpa jeda tidak dianggap bentrok, sama seperti peminjaman.

`RoomBookingService::occupancy($room, $start, $end)` mengembalikan rentang terpakai satu reservasi. Dipakai detail reservasi, kalender, halaman ruang, dan rentang pelengkap.

`validate()` memeriksa berurutan: ruang aktif dan bisa dipesan, jenis diizinkan, jam selesai setelah mulai, waktu belum lewat (untuk reservasi baru), kapasitas, lalu bentrok. Hasilnya `[field => pesan]`, yang oleh halaman Create dan Edit diubah menjadi `ValidationException` dengan kunci `data.{field}`.

### Persetujuan

| Method | Aturan |
|---|---|
| `canApprove()` | Status Diajukan, dan pengguna berperan `kabag_sarpras`/`super_admin`, **atau** penanggung jawab ruang untuk reservasi internal |
| `canEdit()` | Status Diajukan, oleh pemesan atau penyetuju |
| `canCancel()` | Menempati ruang, acara belum lewat, pelengkap tidak sedang dipinjam; oleh pemesan, penanggung jawab ruang, atau penyetuju |

`approve()` mengunci baris ruang (`lockForUpdate`) lalu memeriksa ulang bentrok terhadap reservasi yang sudah **disetujui**. Tanpa kunci itu, dua penyetuju yang membuka dua permohonan untuk jam sama bisa meloloskan keduanya. Bila bentrok, `DomainException` dilempar dan halaman menampilkannya sebagai notifikasi.

### Visibilitas

`App\Models\Scopes\RoomBookingVisibilityScope`:

| Pengguna | Yang terlihat |
|---|---|
| `canSeeAllUnits()` | Semua |
| Lainnya | Milik sendiri (`user_id` atau `created_by`) dan reservasi di ruang yang ia pegang (`rooms.pic_user_id`) |

Scope dilepas di `conflicts()`, `dayAgenda()`, `publicSchedule()`, dan halaman `JadwalRuang`, karena bentrok dan jam terisi harus terlihat utuh. Di kalender, kartu kegiatan hanya bisa diklik oleh yang berhak membuka rinciannya.

### Pelengkap

Pelengkap adalah `Loan` biasa yang ditautkan lewat `room_bookings.loan_id`.

- **Pilihan barang**: `equipmentGroups()` memanggil `LoanService::availableGroups(..., outsideLaboratories: true)` dengan rentang `occupancy()`. Kursi dipinjam sejak persiapan, bukan sejak acara
- **Dibuat saat reservasi disimpan**: `syncEquipment()` membuat pinjaman berstatus Diajukan, sehingga barang terkunci sejak awal. Notifikasi "permohonan baru" dimatikan lewat `LoanObserver::$muteCreatedNotification`
- **Mengikuti reservasi**: disetujui bersama (tanpa notifikasi ganda), ditolak bersama, dibatalkan bersama. Bila seluruh pelengkap dihapus lewat edit, pinjamannya dibatalkan
- **Terkunci di menu Peminjaman**: `LoanResource::canEdit()` dan `canDelete()` menolak, tombol Setujui dan Tolak disembunyikan lewat `Loan::isBookingEquipment()`
- **Serah terima** dari `ViewRoomBooking` memanggil `LoanService::handOut()` dan `handBack()`; `canHandleEquipment()` mengizinkan penyetuju dan penanggung jawab ruang

### Biaya sewa

`RentalFeeService::calculate($room, $start, $end, $tanggalTarif)`:

1. SK berlaku = `RentalDecree::current($tanggal)`, yaitu `effective_from` terbaru yang tidak melewati tanggal
2. Tarif = baris `rental_rates` SK itu untuk ruang tersebut
3. Rentang acara dipecah per hari kalender. Untuk tiap hari dicari tarif termurah dari: seluruh tarif `hari`, dan tarif `sesi` yang `session_hours`-nya mencukupi lama acara hari itu
4. Bila hanya ada tarif sesi dan tidak ada yang cukup, sesi terpanjang dikalikan `ceil(jam / session_hours)`

Hasilnya `total`, `decree`, `rincian` per hari, dan `pesan` bila SK atau tarif belum ada. Persiapan dan beres-beres tidak ikut dihitung karena perhitungan hanya memakai `start_at`–`end_at`.

| Tahap | Method | Isi |
|---|---|---|
| Dibuat atau diubah | `refreshFee()` | Perkiraan disimpan tanpa `payment_status` |
| Disetujui | `lockFee($booking, $fee)` | Dihitung ulang dengan SK saat itu. Nominal dari penyetuju yang berbeda dicatat di `fee_breakdown.penyesuaian`. `payment_status` menjadi BelumBayar bila biaya lebih dari nol |
| Bukti diunggah | `submitPaymentProof()` | Berkas dari disk `local` dipindah ke koleksi `bukti_bayar`; status MenungguVerifikasi |
| Dikonfirmasi | `confirmPayment()` | Lunas, `receipt_number` terbit sekali |

`handOutBlockedReason()` mengembalikan "Biaya sewa belum lunas" bila reservasi eksternal berbiaya belum lunas. Dipakai tombol Serahkan Pelengkap dan `PindaiSerahTerima::serahkan()`.

### Tagihan dan kuitansi

`RentalReceiptController` dengan template `print/sewa.blade.php` (dompdf A4):

| Route | Syarat |
|---|---|
| `/cetak/sewa/{id}/tagihan` | `canPrintInvoice()`: disetujui dan `requiresPayment()` |
| `/cetak/sewa/{id}/kuitansi` | `canPrintReceipt()`: juga lunas dan punya nomor kuitansi |

Reservasi dicari dengan scope visibilitas aktif, sehingga pengguna yang tidak berhak mendapat 404. Terbilang memakai `App\Support\Terbilang::rupiah()`.

### Jadwal Ruang

`App\Filament\Pages\JadwalRuang`, tanpa menu sendiri. Properti `tampilan` (`bulan`/`minggu`), `tanggal`, dan `roomId`; bisa dibuka dengan `?ruang=`, `?tanggal=`, dan `?tampilan=`.

`acaraPadaHari()` memecah tiap reservasi menjadi blok `persiapan`, `acara`, dan `beres`, lalu memotongnya sesuai hari. Rentang query diperlebar 3 hari agar blok persiapan di tepi periode ikut tampil. Tampilan bulan membatasi 3 blok per sel (`MAKS_PER_SEL`).

### Halaman ruang publik

`PublicRoomController::show()` di `/r/{code}`, throttle 60 per menit, `noindex`. Data dari `RoomBookingService::publicSchedule($room, 7)`:

- `sekarang`: kode `dipakai`, `persiapan`, atau `kosong`, beserta kegiatan berjalan atau berikutnya
- `hari`: 7 hari berisi blok dengan jam, judul, dan status menunggu

Tidak ada data pemesan yang dikirim ke view. Tombol Pesan Ruang Ini mengarah ke `RoomBookingResource::getUrl('create').'?ruang='.$id`; field `room_id` di form memakai `->default(fn () => request()->integer('ruang'))`.

---

## 13. Pengaturan Sistem

`spatie/laravel-settings` dengan satu grup: `general`.

### Menambah properti

Urutannya penting karena ada masalah ayam-telur: perintah `make:settings-migration` me-resolve class settings, sehingga gagal bila ada properti yang belum ada di database.

**Cara aman** — buat file migrasi manual di `database/settings/`:

```
2026_08_30_100000_add_something_to_general_settings.php
```

```php
public function up(): void
{
    $this->migrator->add('general.nama_properti', 'nilai_default');
}
```

Jalankan `php artisan migrate`, **baru** tambahkan properti di `app/Settings/GeneralSettings.php`.

### Kelas pembantu

`App\Support\Sapra` menjembatani settings dengan config. Bila settings belum tersedia, misalnya saat seeder atau sebelum migrasi, nilai diambil dari `config/sapra.php`.

```php
Sapra::appName()
Sapra::institutionName()
Sapra::assetCodePrefix()
Sapra::theme()                 // seluruh token warna
Sapra::signatory('wadir')      // ['name' => ..., 'nip' => ...]
Sapra::letterheadDataUri()     // kop surat untuk dompdf
```

Metode ini yang membuat `AssetObserver` tetap berjalan saat dipanggil dari console.

Kelas yang sama menyediakan format rupiah:

```php
Sapra::rupiahRingkas(9500000)     // Rp 9,5 jt
Sapra::rupiahRingkas(150000000)   // Rp 150 jt
Sapra::rupiahRingkas(1250000000)  // Rp 1,3 M
Sapra::rupiah(9500000)            // Rp 9.500.000
```

`rupiahRingkas()` memakai satu desimal dan membuang `,0` di belakang. Dipakai oleh kartu Nilai Aset di dashboard dan kolom Nilai di daftar aset. Jangan membuat salinan fungsi format di kelas lain. Sebelumnya fungsi ini ada di dua tempat, dan kedua salinan itu mudah menghasilkan format yang berbeda.

Halaman detail dan berita acara tetap memakai `Sapra::rupiah()`, karena di sana angka persis lebih dibutuhkan.

`App\Support\Terbilang::rupiah(1750000)` menghasilkan "satu juta tujuh ratus lima puluh ribu rupiah". Dipakai tagihan dan kuitansi sewa.

### Tema

Tiga preset di `config/sapra.php`: `navy`, `teal`, `slate`. Masing-masing berisi sembilan token warna.

Warna kustom hanya menimpa preset bila `use_custom_colors` diaktifkan. Tanpa penanda ini, preset selalu ditimpa oleh nilai lama dan pilihan tema jadi tidak berfungsi.

---

## 14. Notifikasi

Filament database notifications, aktif lewat:

```php
->databaseNotifications()
->databaseNotificationsPolling('30s')
```

`NotifyService` menyediakan dua method:

```php
NotifyService::toRoles(['kabag_sarpras'], $judul, $isi, $url, $warna, $ikon);
NotifyService::toUser($userId, $judul, $isi, $url, $warna, $ikon);
```

### Kejadian yang memicu notifikasi

| Kejadian | Penerima |
|---|---|
| Pengajuan diajukan | Ka. Sarpras |
| Pengajuan diverifikasi | Wadir |
| Pengajuan disetujui / ditolak / minta revisi | Pengaju |
| Pengajuan disetujui | Ka. Sarpras, untuk disalurkan |
| Laporan kerusakan baru | Ka. Sarpras, ditambah laboran lab terkait bila barangnya milik laboratorium |
| Perintah kerja ditugaskan | Teknisi bersangkutan |
| Peminjaman diajukan | Laboran lab bersangkutan, atau Ka. Sarpras bila di luar lab |
| Peminjaman disetujui / ditolak | Peminjam. Penolakan menyertakan alasannya |
| Peminjaman terlambat | Peminjam, dan laboran lab bersangkutan |
| Usulan penghapusan masuk | Ka. Sarpras, lalu Wadir |
| Penghapusan disetujui / ditolak | Pengusul |
| Reservasi ruang dibuat | Penanggung jawab ruang untuk reservasi internal; Ka. Sarpras untuk pihak luar atau ruang tanpa penanggung jawab |
| Reservasi disetujui / ditolak / dibatalkan penyetuju | Pemesan. Persetujuan pihak luar menyebut nominal tagihan |
| Reservasi disetujui dibatalkan pemesan | Penyetuju |
| Bukti bayar sewa diunggah | Ka. Sarpras |
| Pembayaran sewa dikonfirmasi | Pemesan, beserta nomor kuitansi |

Penyaluran, pembuatan berita acara, dan hasil opname sengaja tidak diberi notifikasi. Perubahannya sudah terlihat langsung di layar.

Peminjaman pelengkap reservasi tidak memicu notifikasi "permohonan baru", karena penyetuju sudah menerima notifikasi reservasinya.

### Pengalihan ke laboran

`NotifyService::toLabStaff($laboratoryId, …)` mengirim ke laboran yang bertugas di lab itu. Bila lab tidak ditentukan atau belum punya laboran, notifikasi **jatuh ke Ka. Sarpras** agar tidak hilang.

Ini yang membuat peminjaman aset umum di luar lab tetap sampai ke orang yang tepat.

### Menambah kanal

Notifikasi memakai `sendToDatabase()`. Untuk menambah email atau WhatsApp, ubah `NotifyService::send()` agar juga mengirim lewat kanal lain. Seluruh pemanggil tidak perlu diubah.

---

## 15. Tugas Terjadwal

Didefinisikan di `routes/console.php`.

| Waktu | Tugas |
|---|---|
| Tiap 15 menit | Menandai peminjaman terlambat dan mengirim notifikasi |
| 02:00 harian | Backup database |
| 04:00 harian | Membersihkan arsip backup lama |
| 06:00 harian | Membangkitkan perintah kerja dari jadwal jatuh tempo |
| 03:00 Minggu | Backup lengkap termasuk berkas |

Semua memakai `withoutOverlapping()` agar tidak bertumpuk bila eksekusi sebelumnya belum selesai.

### Memeriksa

```bash
php artisan schedule:list
php artisan schedule:run          # jalankan sekali secara manual
```

---

## 16. Backup dan Pemulihan

### Konfigurasi

`config/backup.php` mencakup database dan `storage/app/public`. Folder `vendor`, `node_modules`, dan log dikecualikan.

Kebijakan penyimpanan: seluruh backup disimpan 7 hari, harian 30 hari, mingguan 8 minggu, bulanan 6 bulan, tahunan 2 tahun. Batas total 5 GB.

### Perintah

```bash
php artisan backup:run --only-db     # database saja
php artisan backup:run               # lengkap
php artisan backup:list              # daftar arsip
php artisan backup:clean             # bersihkan yang lama
```

### mysqldump

Spatie Backup memanggil `mysqldump` eksternal. Jalurnya diatur lewat `DB_DUMP_PATH` di `.env`, **berisi folder saja, bukan nama berkas**.

```env
DB_DUMP_PATH=/usr/bin
```

Di Windows dengan Herd:
```env
DB_DUMP_PATH="C:/Users/nama/.config/herd/bin/mysql/bin"
```

### Prosedur pemulihan

```bash
# 1. Ekstrak arsip
unzip backup-2026-09-01.zip -d /tmp/pulih

# 2. Buat database uji, jangan timpa produksi
mysql -u root -e "CREATE DATABASE simaspra_uji;"

# 3. Muat dump
mysql -u root simaspra_uji < /tmp/pulih/db-dumps/mysql-simaspra.sql

# 4. Periksa isinya
mysql -u root simaspra_uji -e "SELECT COUNT(*) FROM assets;"

# 5. Kembalikan berkas
cp -r /tmp/pulih/storage/app/public/* storage/app/public/
```

**Uji pemulihan minimal setiap tiga bulan.** Backup yang tidak pernah diuji tidak bisa disebut backup.

---

## 17. Keamanan

### Yang sudah diterapkan

**Injeksi SQL** — seluruh query memakai Eloquent atau Query Builder dengan prepared statement. `whereRaw` yang ada di kode hanya berisi nama kolom dan parameter ter-bind, tanpa input pengguna.

**XSS** — Blade auto-escape. HTML mentah lewat `HtmlString` hanya dipakai untuk konten yang dirakit sistem, dan seluruh data pengguna di dalamnya melewati `e()`.

**IDOR** — dokumen memakai kode acak tak berurutan, bukan id. Ditambah pemeriksaan unit di controller:

```php
abort_unless(
    $user->canSeeAllUnits() || $handover->unit_id === $user->unit_id,
    403
);
```

Tanpa ini, siapa pun yang login bisa menebak `/cetak/bast/2` untuk melihat dokumen unit lain.

**Pembatasan laju** — 60 permintaan per menit untuk halaman scan, 5 per jam untuk laporan kerusakan publik.

**Honeypot** — field tersembunyi bernama `website` pada form publik, divalidasi `size:0`. Bot yang mengisinya mendapat respons sukses palsu tanpa data tersimpan.

**Unggahan berkas** — divalidasi tipe MIME, dibatasi ukurannya. Dokumen hanya menerima PDF.

**Data sensitif** — halaman scan publik menyembunyikan nilai perolehan, nomor seri, dan vendor dari pengunjung yang belum login. Halaman depan, halaman masuk, dan halaman ruang hanya menampilkan agregat yang aman; jumlah aset rusak, laporan kerusakan, dan data pemesan ruang tidak pernah dikirim ke view publik.

**Halaman ruang publik** — throttle 60 permintaan per menit dan `noindex`, supaya jadwal ruang tidak masuk mesin pencari.

**Tagihan dan kuitansi** — dicari dengan scope visibilitas reservasi, sehingga pengguna lain mendapat 404, bukan 403 yang mengonfirmasi keberadaan dokumen.

**Penghapusan berita acara** — memerlukan kata sandi akun yang sedang login (`current_password`), dan tetap dibatasi izin `DeleteAny:Handover`.

**Berita acara lintas unit dan lab** — `HandoverVisibilityScope` membuat berita acara di luar wewenang tidak ditemukan, baik di daftar maupun di route cetak.

**Rekap laboratorium** — satu lab per cetakan; laboran yang meminta lab lain mendapat 403.

**Tanpa pendaftaran mandiri** — panel tidak menyediakan formulir registrasi.

**Halaman kesalahan kustom** — 401, 403, 404, dan 500 memakai tampilan sendiri, tidak membocorkan detail sistem.

**Jejak audit** — `spatie/laravel-activitylog` pada `Asset`, `ProcurementRequest`, `Handover`, `WorkOrder`, `Loan`, `Disposal`, `RoomBooking`, dan `RentalDecree`. Perubahan `quantity` aset kolektif ikut tercatat. Perubahan tersimpan di kolom `attribute_changes`, bukan `properties`.

### Yang perlu diperiksa berkala

```bash
composer audit          # kerentanan paket PHP
npm audit               # kerentanan paket JavaScript
```

### Daftar periksa produksi

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Berkas `.env` di luar document root
- [ ] HTTPS aktif dengan HSTS
- [ ] Pengguna database tanpa hak `DROP` dan `GRANT`
- [ ] Debugbar dinonaktifkan atau dicopot
- [ ] Backup terjadwal berjalan dan sudah diuji pulihkan
- [ ] MFA aktif untuk `super_admin`, `wadir`, `kabag_sarpras`
- [ ] Registrasi mandiri tetap nonaktif (`->registration()` tidak dipakai)

---

## 18. Kinerja

### Pencegahan N+1

Seluruh tabel Resource memakai eager loading:

```php
->modifyQueryUsing(fn (Builder $query) => $query->with([
    'category:id,name',
    'unit:id,name',
    'room:id,name,building_id',
    'room.building:id,name',
    'media',
]))
```

**Pemilihan kolom wajib menyertakan foreign key.** Tanpa `building_id` pada contoh di atas, relasi `room.building` gagal dimuat.

**`media` wajib disertakan** pada Resource yang menampilkan foto. Tanpa itu, `getFirstMediaUrl()` memicu query per baris.

### Deteksi saat pengembangan

```php
// app/Providers/AppServiceProvider.php
Model::preventLazyLoading(! app()->isProduction());
```

Setiap relasi yang belum di-eager load akan melempar `LazyLoadingViolationException` beserta nama relasinya. Otomatis nonaktif di produksi.

### Cache statistik

`StatsService` menyimpan hasil agregasi selama 15 menit. Cache dibersihkan otomatis saat ada perubahan aset:

```php
Asset::saved(fn () => StatsService::flush());
Asset::deleted(fn () => StatsService::flush());
```

Method yang menerima parameter, seperti `requestTrend($months)`, wajib menyertakan parameter itu di kunci cache bila di-cache, misalnya `"stats.trend.{$months}"`. Dengan kunci tetap, pilihan 3 bulan akan mengembalikan hasil 12 bulan yang tersimpan lebih dulu.

Kunci cache halaman publik: `stats.public`, `stats.lab`, dan `stats.activity.6`. Ketiganya ikut dibersihkan `flush()`. Peminjaman baru tidak memicu flush, sehingga grafik aktivitas bisa tertinggal sampai 15 menit.

### Front-end

Halaman publik memakai Tailwind hasil build Vite, bukan CDN. CSS akhir sekitar 20 hingga 40 KB, dibandingkan 400 KB plus waktu kompilasi di browser bila memakai CDN.

---

## 19. Pemeliharaan Rutin

### Harian, otomatis

Ditangani scheduler: penandaan keterlambatan, backup, pembersihan arsip, pembangkitan perintah kerja preventif.

### Mingguan

- Periksa log: `storage/logs/laravel.log`
- Periksa backup terbaru berhasil: `php artisan backup:list`

### Bulanan

- `composer audit` dan `npm audit`
- Periksa penggunaan ruang disk, terutama `storage/app/public`
- Tinjau akun nonaktif dan peran pengguna

### Triwulanan

- **Uji pemulihan backup ke database terpisah**
- Tinjau daftar pengguna, nonaktifkan yang sudah tidak berkepentingan
- Perbarui paket minor: `composer update --with-dependencies`

### Tahunan

- Dorong pelaksanaan stock opname menyeluruh
- Arsipkan data lama bila diperlukan
- Tinjau versi mayor Laravel dan Filament

### Setelah menambah Resource

```bash
php artisan shield:generate --all --panel=asetadmin
php artisan db:seed --class=RolePermissionSeeder
php artisan optimize:clear
```

Bila Resource baru punya nama model yang belum ada di seeder, tambahkan ke array `$model` di `RolePermissionSeeder`. Seeder akan membuat izin modelnya sendiri.

Bila pengaturan izin di menu Peran sudah disesuaikan manual, jangan jalankan `RolePermissionSeeder` karena seeder itu menyamakan ulang seluruh izin. Buat seeder khusus modul yang hanya menambah izin, seperti `RoomBookingPermissionSeeder`.

---

## 20. Penanganan Masalah

### Menu tidak muncul setelah menambah Resource

```bash
php artisan shield:generate --all --panel=asetadmin
php artisan db:seed --class=RolePermissionSeeder
php artisan optimize:clear
```

Bila pengaturan izin manual perlu dipertahankan, pakai seeder khusus modul yang hanya menambah izin, lalu `php artisan permission:cache-reset`. Pengguna perlu keluar dan masuk ulang.

Periksa berurutan bila tetap tidak muncul:

```powershell
# 1. Berkas di tempat yang benar
Test-Path app\Filament\Resources\RoomBookings\RoomBookingResource.php

# 2. Cache daftar komponen Filament
php artisan filament:optimize-clear

# 3. Resource terdaftar
php artisan route:list --path=room-bookings

# 4. Izin tercatat
php artisan permission:show web | Select-String "RoomBooking"
```

### Seluruh peran kehilangan permission

Kemungkinan seeder dijalankan sebelum `shield:generate`, sehingga `syncPermissions([])` mengosongkan semuanya.

```bash
php artisan shield:generate --all --panel=asetadmin
php artisan db:seed --class=RolePermissionSeeder
```

Seeder versi terkini melewati peran yang hasilnya nihil, sehingga masalah ini tidak terulang.

### Media tersimpan di folder private

Periksa `config/media-library.php`:

```php
'disk_name' => 'public',
```

Setiap koleksi media juga menyebut disk secara eksplisit:

```php
$this->addMediaCollection('foto')->useDisk('public');
```

Perbaiki, hapus media lama, unggah ulang.

### Foto tidak tampil meski berkas ada

```powershell
Get-Item public\storage | Select-Object LinkType, Target
```

Bila bukan symlink atau junction, hapus lalu jalankan `php artisan storage:link`.

### PDF menghasilkan halaman kosong

Elemen pemisah halaman yang berdiri sendiri dianggap berisi oleh dompdf. Terapkan `page-break-after` pada elemen konten, bukan pada div kosong.

### QR kosong di PDF

dompdf gagal merender SVG. Periksa ekstensi tersedia:

```bash
php -r "var_dump(extension_loaded('gd'), extension_loaded('imagick'));"
```

Bila perlu, ubah `QrService` agar menghasilkan PNG.

### Nama berkas mengandung garis miring

```php
str_replace('/', '-', $nomor)
// atau lebih aman
preg_replace('/[^A-Za-z0-9_-]/', '-', $kode)
```

### Tanggal berbahasa Inggris

```php
$tanggal->locale('id')->translatedFormat('d F Y')
```

`->locale('id')` per pemanggilan lebih dapat diandalkan daripada bergantung pada konfigurasi global.

### Zona waktu meleset

```env
APP_TIMEZONE=Asia/Jakarta
```

```bash
php artisan config:clear
```

Data lama tetap memakai zona waktu saat dibuat.

### Komponen Blade Filament tidak dikenali

Ketersediaan komponen berbeda antar versi. Periksa dulu:

```powershell
Get-ChildItem vendor\filament\support\resources\views\components -Filter *.blade.php
Get-ChildItem vendor\filament\tables\resources\views\components -Filter *.blade.php
```

Yang paling stabil lintas versi: `x-filament::section`, `x-filament::badge`, `x-filament::button`, `x-filament::icon`. Komponen grid dan tabel sering tidak tersedia.

Halaman kustom di `resources/views/filament/pages/` memakai gaya inline, bukan kelas Tailwind. Alasannya, CSS panel Filament terkompilasi terpisah dan tidak memindai berkas Blade kustom. Kelas Tailwind yang ditulis di sana tidak akan tampil kecuali `@source` di `resources/css/app.css` diperluas.

### Teks dropdown tidak terbaca di mode gelap

`<select>` polos memakai warna sistem operasi untuk daftar pilihannya. Beri kelas dengan aturan eksplisit untuk kedua mode:

```css
.sapra-select { background: #fff; color: #101828; }
.dark .sapra-select { background: #1f2937; color: #f9fafb; }
.sapra-select option { background: #fff; color: #101828; }
.dark .sapra-select option { background: #1f2937; color: #f9fafb; }
```

### Filter Livewire tidak memicu perubahan

Livewire 4 ketat soal tipe properti. Properti `?int` yang menerima string dari `<select>` kadang tidak terdeteksi berubah.

Longgarkan tipenya, lalu konversi saat dipakai:

```php
public $labId = null;

protected function labIdInt(): ?int
{
    return filled($this->labId) ? (int) $this->labId : null;
}
```

Atau tambahkan method `updated{Nama}()` kosong, yang keberadaannya saja memaksa render ulang.

### Filter periode grafik berubah, tapi grafiknya tetap

Deskripsi widget berganti, misalnya menjadi "3 bulan terakhir", tetapi grafik tetap menampilkan 12 bulan. Artinya filter terbaca, tapi datanya tidak memakai nilai itu. Dua penyebab yang pernah terjadi:

- `getData()` menghitung data dengan filter ke satu variabel, tetapi dataset diisi dari variabel lain hasil pemanggilan tanpa parameter
- `requestTrend()` tidak menerima parameter. PHP tidak menolak argumen berlebih pada method biasa, jadi `requestTrend(3)` tidak memunculkan error, dan angka 3 itu diabaikan

Periksa tanda tangannya:

```powershell
Get-Content app\Services\StatsService.php | Select-String "function requestTrend" -Context 0,4
```

Bila method itu di-cache, pastikan juga kunci cache menyertakan jumlah bulan. Lihat bab 18.

### The relationship [laboratory] does not exist on the model [App\Models\Asset]

Muncul bila filter Lab ditulis dengan `->relationship('laboratory', 'code')`. Aset terhubung ke lab lewat ruangan, bukan langsung. Pakai filter manual dengan scope `inLaboratory()` seperti di bab 11.

### Peminjaman lama dianggap terlambat setelah konversi ke DATETIME

Migrasi konversi seharusnya menaikkan jam akhir ke `23:59:59`. Periksa:

```sql
SELECT loan_number, end_at FROM loans WHERE TIME(end_at) = '00:00:00';
```

Bila ada, perbaiki manual:

```sql
UPDATE loans SET end_at = TIMESTAMP(DATE(end_at), '23:59:59')
WHERE TIME(end_at) = '00:00:00';
```

### Alat tampak tersedia padahal sedang dipinjam

`conflictingAssetIds()` tidak melepas `LoanVisibilityScope`. Tanpa pelepasan itu, pengecekan hanya melihat pinjaman milik pengguna yang sedang login.

Pastikan ada baris:

```php
->withoutGlobalScope(LoanVisibilityScope::class)
```

Ini kesalahan yang paling merusak di modul lab, karena akibatnya baru ketahuan saat dua orang datang mengambil alat yang sama.

Kini pengecekan memakai `Loan::reservedQuantities()`, dengan aturan pelepasan scope yang sama. Pengecekan bentrok reservasi (`RoomBookingService::conflicts()`) juga melepas `RoomBookingVisibilityScope`.

### Call to a member function newQueryWithoutRelationships() on null

Terjadi saat membuka halaman daftar yang memakai tab atau filter kustom, tanpa frame kode aplikasi di jejak error.

Penyebabnya: Filament mengisi parameter closure berdasarkan **nama**. Untuk `Tab::modifyQueryUsing()`, filter, dan kolom, nama yang dikenali adalah `$query`, `$record`, `$state`, `$get`, `$set`, `$data`. Closure `fn (Builder $q) => ...` membuat Filament mencoba membuat `Builder` baru dari container. Builder itu tidak punya model, dan pemanggilan `where(Closure)` berikutnya gagal.

```php
// Salah
->modifyQueryUsing(fn (Builder $q) => $q->where(...))

// Benar
->modifyQueryUsing(fn (Builder $query) => $query->where(...))
```

Nama bebas hanya aman di closure yang dipanggil Laravel secara posisional, misalnya `->when($nilai, fn ($q, $v) => ...)`.

### Direktif Blade tidak terbaca setelah @endif

`@endif@if (...)` yang ditulis rapat tidak dikenali sebagai dua direktif, karena Blade mensyaratkan `@` tidak didahului huruf. Beri spasi: `@endif @if (...)`.

### Policy [ViewAny:RoomBooking] not found di editor

Peringatan dari ekstensi Laravel di VS Code atau Windsurf, bukan error aplikasi. Ekstensi hanya mengenali ability Gate dan Policy, sedangkan `ViewAny:RoomBooking` adalah nama izin Spatie yang baru terdaftar ke Gate saat aplikasi berjalan. Untuk menghindari peringatan, panggil lewat Resource: `RoomBookingResource::canViewAny()`.

### Seluruh tombol Perintah Kerja error 500

`MaintenanceService` pernah memuat dua method `start()`. PHP gagal memuat kelas itu hanya saat kelasnya dipanggil, sehingga halaman lain tetap berjalan normal. Periksa sintaks seluruh berkas setelah menimpa kode:

```powershell
Get-ChildItem app -Recurse -Filter *.php |
    ForEach-Object { php -l $_.FullName } |
    Select-String -NotMatch "No syntax errors"
```

### Tombol Tugaskan tidak muncul pada perintah kerja baru

Syarat `visible()` harus mencakup status Dilaporkan. Versi lama hanya menampilkan tombol pada status Ditugaskan, sehingga perintah kerja dari laporan kerusakan macet.

### Impor Excel membuat ratusan aset untuk kursi

Kolom `kolektif` kosong atau berkas memakai templat lama. `AssetImport` hanya membuat satu aset kolektif bila `kolektif` bernilai Ya dan `jumlah` lebih dari 1.

### Laboran mendapat 403 saat login

Terjadi bila `Dashboard` memakai `HasPageShield` tanpa `homeUrl`: login diarahkan ke Beranda utama, sedangkan laboran tidak berizin. Pasang `homeUrl` seperti di bab 7, dan pastikan `Dashboard::canAccess()` selalu benar dengan pengalihan di `mount()`.

### Halaman beranda berputar tanpa henti

`homeUrl` memakai `Dashboard::canAccess()`, yang kini selalu benar. Ganti dengan `Dashboard::canView()`.

### Tombol Pinjam Alat Massal Saja tidak menampilkan apa pun

Section peminjaman baru dulu mensyaratkan keranjang tidak kosong. Kondisinya harus cukup `@if ($aksi === 'baru')`.

### Laporan kerusakan alat massal gagal tersimpan

Migrasi `add_consumable_to_damage_reports` belum dijalankan, sehingga `asset_id` masih wajib diisi.

### Aset kolektif ikut berstatus Perbaikan atau Diusulkan Dihapus

`WorkOrderObserver` harus melewati aset kolektif. Periksa blok `if ($asset->isCollective())` di awal `updated()`.

---

## 21. Pengembangan Lanjutan

### Penyusutan aset

Tabel `depreciations` dan method `Depreciation::calculateFor()` sudah ada dan berfungsi. Perhitungan memakai metode garis lurus dengan nilai residu opsional per kategori. Method bersifat idempoten, menjalankan ulang untuk tahun yang sama akan menimpa, bukan menggandakan.

**Yang belum ada:** perintah artisan untuk menjalankan massal, dan Resource untuk menampilkan hasilnya.

```php
// Kerangka perintah
foreach (Asset::allUnits()->active()->cursor() as $asset) {
    Depreciation::calculateFor($asset, $tahun);
}
```

Perkiraan pekerjaan: satu perintah artisan, satu Resource baca-saja, satu tombol di panel. Sekitar 80 baris.

### Saran pengembangan lain

**Ekspor laporan ke Excel.** Paket `maatwebsite/excel` sudah terpasang dan dipakai untuk impor aset dan pengguna. Menambah ekspor rekap lab atau daftar aset relatif singkat.

**Pemindai QR berbasis kamera.** Halaman Pindai Serah Terima saat ini mengandalkan alat pemindai USB atau pengetikan manual. Menambahkan `html5-qrcode` memungkinkan pemindaian lewat kamera ponsel tanpa aplikasi tambahan. Butuh HTTPS, yang sudah tersedia.

**Perpanjangan peminjaman.** Saat ini peminjam yang butuh waktu lebih lama harus mengajukan ulang. Tombol perpanjang dengan persetujuan laboran akan mengurangi keterlambatan yang tidak disengaja.

**Pembatasan otomatis peminjam bermasalah.** Data keterlambatan sudah tercatat. Aturan yang menolak pengajuan baru bila peminjam masih punya tunggakan tinggal ditambahkan di `LoanService`.

**Jadwal praktikum terintegrasi.** Bila jadwal kuliah tersedia, alat bisa dipesan otomatis untuk sesi praktikum terjadwal, mengurangi pengajuan manual.

**Notifikasi WhatsApp otomatis.** `NotifyService` sudah terpusat. Mengganti `wa.me` dengan gateway berbayar cukup mengubah satu method, dan status pengiriman jadi bisa diverifikasi.

**Layar jadwal di depan ruang.** `publicSchedule()` sudah menyediakan data jadwal. Halaman `/r/{code}` bisa diberi mode layar penuh yang menyegarkan diri, untuk dipasang di tablet di depan aula.

**Pengingat pembayaran sewa.** Reservasi pihak luar yang belum lunas menjelang hari acara bisa diingatkan lewat pola yang sama dengan `ReminderService`.

**Pengembalian sebagian pelengkap.** Saat ini pelengkap dikembalikan sekaligus. Pengembalian bertahap memerlukan beberapa BAPK per peminjaman.

**Ekspor untuk PDDikti dan borang akreditasi.** Data sudah tersedia; yang dibutuhkan adalah pemetaan ke format yang diminta.

### Panduan menambah modul baru

1. Buat migrasi dan model. Pakai `BelongsToUnit` bila data bersifat per unit.
2. Buat enum untuk status, implementasikan `HasLabel` dan `HasColor`.
3. Buat layanan di `app/Services` untuk logika bisnisnya.
4. Buat Observer bila ada efek samping pada model lain.
5. Buat Resource Filament. Sertakan `modifyQueryUsing()` untuk eager loading.
6. Jalankan `shield:generate`, tambahkan ke `RolePermissionSeeder`.
7. Tambahkan notifikasi di layanan bila ada yang perlu diberi tahu.

---

## 22. Lampiran

### A. Perintah artisan yang sering dipakai

```bash
# Pengembangan
php artisan optimize:clear
php artisan migrate
php artisan tinker

# Hak akses
php artisan shield:generate --all --panel=asetadmin
php artisan shield:super-admin
php artisan db:seed --class=RolePermissionSeeder

# Pemeliharaan
php artisan backup:run --only-db
php artisan backup:list
php artisan schedule:list
php artisan schedule:run

# Produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

### B. Route publik

| Metode | URL | Nama | Keterangan |
|---|---|---|---|
| GET | `/` | `home` | Halaman depan |
| GET | `/cek` | `asset.check` | Form cek aset |
| POST | `/cek` | `asset.check.submit` | Pencarian |
| GET | `/a/{code}` | `asset.public` | Informasi aset dari QR |
| POST | `/a/{code}/lapor` | `asset.public.report` | Lapor kerusakan |
| POST | `/a/{code}/opname` | `asset.public.opname` | Catat opname, perlu login |
| GET | `/cetak/label/{asset}` | `label.single` | Label satuan |
| GET | `/cetak/label` | `label.batch` | Label massal |
| GET | `/cetak/bast/{handover}` | `bast.print` | Berita acara |
| GET | `/cetak/opname/{stockOpname}` | `opname.print` | BA inventarisasi |
| GET | `/cetak/kir/{room}` | `kir.print` | Kartu inventaris ruangan |
| GET | `/cetak/rekap-lab` | `rekap.lab.print` | Rekap peminjaman satu laboratorium |
| GET | `/r/{code}` | `room.public` | Jadwal ruang dari QR, tanpa login |
| GET | `/cetak/qr-ruang/{room}` | `room.qr.print` | Papan QR ruang A5 |
| GET | `/cetak/sewa/{id}/tagihan` | `rental.invoice` | Tagihan sewa ruang |
| GET | `/cetak/sewa/{id}/kuitansi` | `rental.receipt` | Kuitansi sewa ruang |
| GET | `/templat/aset` | `asset.template` | Templat impor Excel |

Seluruh route cetak memerlukan autentikasi dan memeriksa kesesuaian unit. Route `/r/{code}` sengaja terbuka tanpa login; route sewa memeriksa visibilitas reservasi.

### C. Format kolom Excel untuk impor

| Kolom | Wajib | Keterangan |
|---|---|---|
| `nama_barang` | Ya | |
| `kode_kategori` | Ya | Harus sudah terdaftar |
| `kode_unit` | Ya | Harus sudah terdaftar |
| `kode_ruang` | Tidak | |
| `kode_vendor` | Tidak | |
| `merek`, `model` | Tidak | |
| `jumlah` | Tidak | Default 1 |
| `kolektif` | Tidak | Ya atau Tidak. Ya dengan jumlah lebih dari 1 membuat satu aset kolektif (maks. 100.000 unit); selain itu satu aset per unit (maks. 200 per baris) |
| `nomor_seri` | Tidak | Diabaikan bila jumlah lebih dari 1 |
| `tahun_perolehan` | Tidak | Default tahun berjalan |
| `tanggal_perolehan` | Tidak | Menerima format Excel maupun teks |
| `sumber` | Tidak | Pembelian, Hibah, Bantuan, Produksi Sendiri |
| `nilai_perolehan` | Tidak | **Per unit.** Menerima `9.500.000` maupun `9500000`. Untuk aset kolektif, sistem menyimpan nilai per unit × jumlah |
| `kondisi` | Tidak | Baik, Rusak Ringan, Rusak Berat |
| `garansi_sampai` | Tidak | |
| `dapat_dipinjam` | Tidak | Ya atau Tidak |
| `peminjam` | Tidak | Semua atau Dosen Saja |
| `catatan` | Tidak | |

Kode aset dan kode QR dibangkitkan otomatis, tidak perlu diisi.

### D. Informasi yang perlu dicatat saat serah terima

Isi dan simpan terpisah dari dokumen ini:

- Alamat produksi dan alamat pengembangan
- Kredensial database
- Akun super admin awal
- Lokasi repositori kode
- Lokasi penyimpanan backup
- Kontak penanggung jawab teknis
- Jadwal pemeliharaan yang disepakati

---

*Dokumen ini menggambarkan sistem pada saat serah terima. Perbarui setiap kali ada perubahan arsitektur, penambahan modul, atau kenaikan versi paket utama.*

