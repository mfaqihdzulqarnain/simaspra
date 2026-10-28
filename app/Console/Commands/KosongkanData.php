<?php

namespace App\Console\Commands;

use App\Models\RentalDecree;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Mengosongkan data untuk persiapan produksi.
 *
 * Yang dipertahankan: akun super_admin, peran dan izin (Shield), pengaturan
 * sistem (identitas, logo, kop, pejabat), dan riwayat migrasi.
 * Dengan --keep-master, data master (unit, gedung, ruang, kategori, vendor,
 * kontrak, laboratorium, SK tarif) juga dipertahankan.
 *
 *   php artisan sapra:kosongkan
 *   php artisan sapra:kosongkan --keep-master
 */
class KosongkanData extends Command
{
    protected $signature = 'sapra:kosongkan
        {--keep-master : Pertahankan data master (unit, gedung, ruang, kategori, vendor, kontrak, lab, SK tarif)}
        {--force : Lewati konfirmasi}';

    protected $description = 'Kosongkan seluruh data kecuali super admin, peran, izin, dan pengaturan sistem';

    /** Tidak pernah dikosongkan */
    protected array $selaluSimpan = [
        'migrations',
        'users',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'settings',
    ];

    /** Disimpan bila --keep-master */
    protected array $master = [
        'units',
        'buildings',
        'rooms',
        'asset_categories',
        'vendors',
        'contracts',
        'laboratories',
        'rental_decrees',
        'rental_rates',
    ];

    public function handle(): int
    {
        $simpanMaster = (bool) $this->option('keep-master');
        $adminIds = User::role('super_admin')->pluck('id')->all();

        if (empty($adminIds)) {
            $this->error('Tidak ada akun super_admin. Dibatalkan agar Anda tidak terkunci dari sistem.');

            return self::FAILURE;
        }

        $simpan = $simpanMaster ? [...$this->selaluSimpan, ...$this->master] : $this->selaluSimpan;

        // Hanya tabel di database aplikasi. Tanpa saringan ini, getTables()
        // ikut mengembalikan tabel dari database lain di server MySQL yang sama.
        $database = DB::connection()->getDatabaseName();

        $semuaTabel = collect(Schema::getTables($database))
            ->filter(fn (array $t) => ($t['schema'] ?? $database) === $database)
            ->pluck('name')
            ->unique()
            ->values();
        $dikosongkan = $semuaTabel->reject(fn ($t) => in_array($t, $simpan, true))->values();

        $this->warn('Database : '.DB::connection()->getDatabaseName());
        $this->line('Dipertahankan : '.User::whereIn('id', $adminIds)->pluck('email')->implode(', ')
            .' (super_admin), peran, izin, pengaturan sistem'.($simpanMaster ? ', data master' : ''));
        $this->line('Dikosongkan   : '.$dikosongkan->count().' tabel, akun selain super_admin, berkas unggahan');
        $this->newLine();

        if (! $this->option('force')) {
            $jawab = $this->ask('Tindakan ini TIDAK BISA DIBATALKAN. Ketik KOSONGKAN untuk melanjutkan');

            if ($jawab !== 'KOSONGKAN') {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        // Media yang dihapus: semua, kecuali berkas SK tarif bila data master dipertahankan
        $mediaDihapus = Schema::hasTable('media')
            ? DB::table('media')
                ->when($simpanMaster, fn ($q) => $q->where('model_type', '!=', RentalDecree::class))
                ->pluck('id')
                ->all()
            : [];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($dikosongkan as $tabel) {
                if ($tabel === 'media' && $simpanMaster) {
                    DB::table('media')->whereIn('id', $mediaDihapus)->delete();
                    $this->line("  · media: {$this->jumlah($mediaDihapus)} berkas dihapus, SK tarif dipertahankan");

                    continue;
                }

                DB::table($tabel)->truncate();
                $this->line("  · {$tabel}");
            }

            // Akun selain super_admin beserta perannya
            $terhapus = DB::table('users')->whereNotIn('id', $adminIds)->delete();

            foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
                DB::table($pivot)
                    ->where('model_type', User::class)
                    ->whereNotIn('model_id', $adminIds)
                    ->delete();
            }

            $this->line("  · users: {$terhapus} akun dihapus");

            // Rujukan ke data yang sudah tidak ada
            if ($simpanMaster) {
                DB::table('rooms')->whereNotIn('pic_user_id', $adminIds)->update(['pic_user_id' => null]);
                DB::table('units')->whereNotIn('head_user_id', $adminIds)->update(['head_user_id' => null]);
                DB::table('laboratories')->whereNotIn('head_user_id', $adminIds)->update(['head_user_id' => null]);
            } else {
                DB::table('users')->whereIn('id', $adminIds)->update(['unit_id' => null]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Berkas unggahan: folder MediaLibrary bernama id media
        $public = Storage::disk('public');

        foreach ($mediaDihapus as $id) {
            $public->deleteDirectory((string) $id);
        }

        foreach (['bukti-bayar-sementara', 'livewire-tmp'] as $folder) {
            Storage::disk('local')->deleteDirectory($folder);
        }

        $this->call('permission:cache-reset');
        $this->call('optimize:clear');

        $this->newLine();
        $this->info('Selesai. Masuk kembali dengan akun super_admin.');

        return self::SUCCESS;
    }

    protected function jumlah(array $ids): int
    {
        return count($ids);
    }
}
