<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin modul Reservasi Ruang.
 *
 * Hanya MENAMBAH izin ke peran; izin lain yang sudah diatur lewat
 * menu Peran tidak disentuh. Aman dijalankan berulang.
 *
 *   php artisan db:seed --class=RoomBookingPermissionSeeder
 */
class RoomBookingPermissionSeeder extends Seeder
{
    protected string $model = 'RoomBooking';

    protected array $semuaAksi = [
        'View', 'ViewAny', 'Create', 'Update', 'Delete', 'DeleteAny',
        'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder',
    ];

    /** Membuat dan mengubah reservasi milik sendiri; policy membatasi ke yang masih menunggu */
    protected array $pemesan = ['View', 'ViewAny', 'Create', 'Update'];

    public function run(): void
    {
        foreach ($this->semuaAksi as $aksi) {
            Permission::firstOrCreate([
                'name' => $aksi.':'.$this->model,
                'guard_name' => 'web',
            ]);
        }

        $peta = [
            'super_admin' => $this->semuaAksi,
            'kabag_sarpras' => $this->semuaAksi,
            'pimpinan' => $this->pemesan,
            'wadir' => $this->pemesan,
            'kabag' => $this->pemesan,
            'kaprodi' => $this->pemesan,
            'dosen' => $this->pemesan,
            'laboran' => $this->pemesan,
            'staf_ruang' => $this->pemesan,
            'teknisi' => $this->pemesan,
            'mahasiswa' => $this->pemesan,
            'peminjam_eksternal' => $this->pemesan,
        ];

        foreach ($peta as $peran => $aksi) {
            $role = Role::where('name', $peran)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->command?->warn(sprintf('  %-22s tidak ada, dilewati', $peran));

                continue;
            }

            $role->givePermissionTo(array_map(fn ($a) => $a.':'.$this->model, $aksi));
            $this->command?->line(sprintf('  %-22s +%d izin reservasi', $peran, count($aksi)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Izin Reservasi Ruang selesai ditetapkan.');
    }
}
