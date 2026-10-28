<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin menu Data Master → Tarif Sewa.
 *
 * Hanya MENAMBAH izin ke peran; izin lain tidak disentuh. Aman dijalankan berulang.
 *
 *   php artisan db:seed --class=RentalDecreePermissionSeeder
 */
class RentalDecreePermissionSeeder extends Seeder
{
    protected string $model = 'RentalDecree';

    protected array $semuaAksi = [
        'View', 'ViewAny', 'Create', 'Update', 'Delete', 'DeleteAny',
        'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder',
    ];

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
            // Pimpinan cukup melihat SK dan tarifnya
            'pimpinan' => ['View', 'ViewAny'],
            'wadir' => ['View', 'ViewAny'],
        ];

        foreach ($peta as $peran => $aksi) {
            $role = Role::where('name', $peran)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->command?->warn(sprintf('  %-22s tidak ada, dilewati', $peran));

                continue;
            }

            $role->givePermissionTo(array_map(fn ($a) => $a.':'.$this->model, $aksi));
            $this->command?->line(sprintf('  %-22s +%d izin tarif sewa', $peran, count($aksi)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Izin Tarif Sewa selesai ditetapkan.');
    }
}
