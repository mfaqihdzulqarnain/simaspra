<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hak akses seluruh peran SIMASPRA.
 *
 *   php artisan db:seed --class=RolePermissionSeeder
 *
 * Perhatian: seeder ini MENYAMAKAN izin setiap peran dengan matriks di bawah
 * (syncPermissions). Izin yang diatur manual lewat menu Peran akan tertimpa.
 * Untuk menambah izin satu modul saja tanpa menyentuh yang lain, pakai seeder
 * khusus modul, misalnya RoomBookingPermissionSeeder.
 *
 * Izin model (Aksi:Model) dibuat otomatis bila belum ada, jadi modul baru
 * cukup didaftarkan di $model. Izin widget dan halaman tetap berasal dari
 * shield:generate.
 */
class RolePermissionSeeder extends Seeder
{
    /** Aksi standar Shield untuk setiap model */
    protected array $aksiStandar = [
        'View', 'ViewAny', 'Create', 'Update', 'Delete', 'DeleteAny',
        'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder',
    ];

    /* --------------------------- Kelompok aksi --------------------------- */

    protected array $baca = ['View', 'ViewAny'];

    protected array $setuju = ['View', 'ViewAny', 'Update'];

    protected array $kelola = ['View', 'ViewAny', 'Create', 'Update', 'Delete'];

    protected array $penuh = [
        'View', 'ViewAny', 'Create', 'Update',
        'Delete', 'DeleteAny', 'Restore', 'RestoreAny',
    ];

    /** Halaman khusus laboratorium, bukan Pengaturan Sistem */
    protected function halamanLab(): array
    {
        return collect($this->semua)
            ->filter(fn (string $p) => str_contains($p, 'Beranda')
                || str_contains($p, 'Laboran')
                || str_contains($p, 'Pindai')
                || str_contains($p, 'Rekap'))
            ->values()
            ->all();
    }

    protected function halamanRekap(): array
    {
        return collect($this->semua)
            ->filter(fn (string $p) => str_contains($p, 'Rekap'))
            ->values()
            ->all();
    }

    /** Cukup untuk mengajukan dan memantau pinjaman sendiri */
    protected array $ajukan = ['View', 'ViewAny', 'Create'];

    /** Reservasi ruang: ubah dibatasi policy ke milik sendiri yang masih menunggu */
    protected array $pesan = ['View', 'ViewAny', 'Create', 'Update'];

    /* ---------------------- Kunci Indonesia ke model ---------------------- */

    protected array $model = [
        'unit' => 'Unit',
        'gedung' => 'Building',
        'ruangan' => 'Room',
        'kategori' => 'AssetCategory',
        'vendor' => 'Vendor',
        'kontrak' => 'Contract',
        'aset' => 'Asset',
        'mutasi' => 'AssetMovement',
        'opname' => 'StockOpname',
        'habis_pakai' => 'Consumable',
        'pengajuan' => 'ProcurementRequest',
        'berita_acara' => 'Handover',
        'peminjaman' => 'Loan',
        'penghapusan' => 'Disposal',
        'kerusakan' => 'DamageReport',
        'perintah_kerja' => 'WorkOrder',
        'jadwal' => 'MaintenanceSchedule',
        'pengguna' => 'User',
        'peran' => 'Role',
        'laboratorium' => 'Laboratory',
        'reservasi' => 'RoomBooking',
        'tarif' => 'RentalDecree',
        'laboratorium' => 'Laboratory',
        'reservasi' => 'RoomBooking',
        'tarif' => 'RentalDecree',
    ];

    protected array $semua = [];

    public function run(): void
    {
        $dibuat = $this->pastikanIzinModel();

        $this->semua = Permission::pluck('name')->all();

        $this->command->info('Total permission: '.count($this->semua)
            .($dibuat ? ' ('.$dibuat.' izin model baru dibuat)' : ''));

        $this->periksaWidgetDanHalaman();

        $this->command->newLine();
        $this->command->info('Menetapkan hak akses...');

        foreach ($this->matriks() as $peran => $izin) {
            $role = Role::firstOrCreate(['name' => $peran, 'guard_name' => 'web']);

            if ($peran === 'super_admin') {
                $role->syncPermissions($this->semua);
                $this->command->line(sprintf('  %-22s %d izin (semua)', $peran, count($this->semua)));

                continue;
            }

            $valid = array_values(array_intersect($izin, $this->semua));

            // Jangan kosongkan peran yang sudah punya izin bila hasilnya nihil
            if (empty($valid) && $role->permissions()->exists()) {
                $this->command->warn(sprintf('  %-22s dilewati, izin lama dipertahankan', $peran));

                continue;
            }

            $role->syncPermissions($valid);
            $this->command->line(sprintf('  %-22s %d izin', $peran, count($valid)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->newLine();
        $this->command->info('Selesai.');
    }

    /**
     * Buat izin Aksi:Model yang belum ada untuk setiap model di $model.
     * Modul baru tidak perlu menunggu shield:generate.
     */
    protected function pastikanIzinModel(): int
    {
        $ada = Permission::pluck('name')->flip();
        $dibuat = 0;

        foreach ($this->model as $model) {
            foreach ($this->aksiStandar as $aksi) {
                $nama = $aksi.':'.$model;

                if ($ada->has($nama)) {
                    continue;
                }

                Permission::create(['name' => $nama, 'guard_name' => 'web']);
                $dibuat++;
            }
        }

        return $dibuat;
    }

    /** Izin widget dan halaman hanya dibuat oleh shield:generate */
    protected function periksaWidgetDanHalaman(): void
    {
        if (empty($this->widget())) {
            $this->command->warn('  Izin widget belum ada. Bila dashboard kosong untuk peran selain super_admin, jalankan:');
            $this->command->line('    php artisan shield:generate --all --panel=asetadmin');
        }
    }

    /* ------------------------------- Matriks ------------------------------ */

    protected function matriks(): array
    {
        return [
            'super_admin' => [],

            'pimpinan' => array_merge(
                $this->izin(['View', 'ViewAny'], ['tarif']),
                $this->izin($this->pesan, ['reservasi']),
                $this->izin($this->baca, [
                    'unit', 'gedung', 'ruangan', 'kategori', 'vendor', 'kontrak',
                    'aset', 'mutasi', 'opname', 'habis_pakai',
                    'pengajuan', 'berita_acara', 'peminjaman', 'penghapusan',
                    'kerusakan', 'perintah_kerja', 'jadwal',
                ]),
                $this->widget(),
                $this->halamanRekap(),
            ),

            'wadir' => array_merge(
                $this->izin(['View', 'ViewAny'], ['tarif']),
                $this->izin($this->pesan, ['reservasi']),
                // Update diperlukan agar tombol Setujui dan Tolak berfungsi
                $this->izin($this->setuju, ['pengajuan', 'penghapusan']),
                $this->izin($this->baca, [
                    'unit', 'gedung', 'ruangan', 'kategori', 'vendor', 'kontrak',
                    'aset', 'mutasi', 'opname', 'habis_pakai',
                    'berita_acara', 'peminjaman',
                    'kerusakan', 'perintah_kerja', 'jadwal',
                ]),
                $this->widget(),
                $this->halamanRekap(),
            ),

            'kabag_sarpras' => array_merge(
                // Seluruh permission kecuali Peran dan Pengguna
                $this->semuaKecuali(['Role', 'User']),

                // Pengguna: hanya lihat dan ubah
                $this->izin(['View', 'ViewAny', 'Update'], ['pengguna']),
            ),

            'kabag' => array_merge(
                $this->izin($this->pesan, ['reservasi']),
                $this->izin($this->kelola, ['pengajuan', 'penghapusan', 'peminjaman']),
                $this->izin(['View', 'ViewAny', 'Create'], ['kerusakan']),
                $this->izin($this->baca, [
                    'aset', 'ruangan', 'gedung', 'kategori', 'unit',
                    'berita_acara', 'perintah_kerja', 'habis_pakai',
                ]),
                $this->widget('StatsOverview'),
            ),

            // Identik dengan kabag; pembeda ada pada unit_id pengguna
            'kaprodi' => array_merge(
                $this->izin($this->pesan, ['reservasi']),
                $this->izin($this->kelola, ['pengajuan', 'penghapusan', 'peminjaman']),
                $this->izin(['View', 'ViewAny', 'Create'], ['kerusakan']),
                $this->izin($this->baca, [
                    'aset', 'ruangan', 'gedung', 'kategori', 'unit',
                    'berita_acara', 'perintah_kerja', 'habis_pakai',
                ]),
                $this->widget('StatsOverview'),
            ),

            'staf_ruang' => array_merge(
                $this->izin($this->pesan, ['reservasi']),
                $this->izin($this->setuju, ['opname', 'habis_pakai']),
                $this->izin(['View', 'ViewAny', 'Create'], ['kerusakan']),
                $this->izin($this->baca, ['aset', 'ruangan']),
            ),

            'teknisi' => array_merge(
                $this->izin($this->pesan, ['reservasi']),
                $this->izin($this->setuju, ['perintah_kerja']),
                $this->izin($this->baca, ['kerusakan', 'jadwal', 'aset', 'vendor']),
                $this->widget('StatsOverview'),
            ),

            'laboran' => array_merge(
                $this->izin($this->pesan, ['reservasi']),
                // Peminjaman: setujui, serahkan, terima kembali, catat langsung
                $this->izin($this->kelola, ['peminjaman']),
                $this->izin(['View', 'ViewAny', 'Create'], ['kerusakan']),
                $this->izin($this->setuju, ['perintah_kerja']),

                // Barang habis pakai dan alat massal: catat masuk, keluar, penyesuaian
                $this->izin($this->kelola, ['habis_pakai']),

                $this->izin($this->baca, [
                    'aset', 'ruangan', 'gedung', 'kategori',
                    'laboratorium', 'berita_acara', 'jadwal',
                ]),
                $this->widget('StatsOverview'),
                $this->halamanLab(),
            ),

            'dosen' => array_merge(
                $this->izin($this->pesan, ['reservasi']),
                $this->izin($this->ajukan, ['peminjaman']),
                $this->izin(['View', 'ViewAny', 'Create'], ['kerusakan']),
                // Lihat ketersediaan alat massal saat meminjam (tahap 2)
                $this->izin($this->baca, ['laboratorium', 'habis_pakai']),
            ),

            'mahasiswa' => array_merge(
                $this->izin($this->pesan, ['reservasi']),
                $this->izin($this->ajukan, ['peminjaman']),
                $this->izin(['View', 'ViewAny', 'Create'], ['kerusakan']),
                $this->izin($this->baca, ['habis_pakai']),
            ),

            'peminjam_eksternal' => array_merge(
                $this->izin(['View', 'ViewAny', 'Create'], ['peminjaman']),
                $this->izin($this->pesan, ['reservasi']),
            ),
        ];
    }

    /* ------------------------------ Pembantu ------------------------------ */

        /**
     * Seluruh permission yang ada, kecuali untuk model tertentu.
     * Nama permission berformat Aksi:Model, jadi pencocokan pakai sufiks.
     */
    protected function semuaKecuali(array $kecualiModel): array
    {
        return collect($this->semua)
            ->reject(function (string $p) use ($kecualiModel) {
                foreach ($kecualiModel as $model) {
                    if (str_ends_with($p, ':' . $model)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }
    
    /** Rangkai nama permission: Aksi:Model */
    protected function izin(array $aksi, array $kunci): array
    {
        $hasil = [];

        foreach ($kunci as $k) {
            $model = $this->model[$k] ?? null;

            if (! $model) {
                continue;
            }

            foreach ($aksi as $a) {
                $hasil[] = $a.':'.$model;
            }
        }

        return $hasil;
    }

    /** Permission widget yang benar-benar ada */
    protected function widget(?string $filter = null): array
    {
        return collect($this->semua)
            ->filter(fn (string $p) => str_contains($p, 'Widget') || str_starts_with($p, 'widget_'))
            ->when($filter, fn ($c) => $c->filter(fn (string $p) => str_contains($p, $filter)))
            ->values()
            ->all();
    }

    /** Permission halaman kustom, termasuk Pengaturan Sistem */
    protected function halaman(): array
    {
        return collect($this->semua)
            ->filter(fn (string $p) => str_contains($p, 'Page')
                || str_starts_with($p, 'page_')
                || str_contains($p, 'ManageGeneralSettings'))
            ->values()
            ->all();
    }
}
