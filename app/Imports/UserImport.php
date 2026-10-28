<?php

namespace App\Imports;

use App\Models\Unit;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Spatie\Permission\Models\Role;

class UserImport implements ToCollection, WithHeadingRow
{
    public int $dibuat = 0;

    public int $diperbarui = 0;

    public array $gagal = [];

    /** Peran yang boleh dibuat lewat impor */
    protected array $peranDiizinkan = ['dosen', 'mahasiswa', 'laboran', 'staf_ruang', 'teknisi'];

    protected array $unitCache = [];

    protected array $peranCache = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $baris = $i + 2; // baris 1 berisi judul kolom

            if (blank($row['nama'] ?? null) && blank($row['email'] ?? null)) {
                continue;
            }

            try {
                $this->simpan($row, $baris);
            } catch (\Throwable $e) {
                $this->gagal[] = "Baris {$baris}: " . $e->getMessage();
            }
        }
    }

    protected function simpan($row, int $baris): void
    {
        $peran = strtolower(trim((string) ($row['peran'] ?? '')));

        if (! in_array($peran, $this->peranDiizinkan, true)) {
            throw new \RuntimeException(
                'Peran "' . $peran . '" tidak diizinkan. Pilihan: ' . implode(', ', $this->peranDiizinkan)
            );
        }

        if (! $this->peranAda($peran)) {
            throw new \RuntimeException('Peran "' . $peran . '" belum terdaftar di sistem.');
        }

        $nim = $this->teks($row['nim'] ?? null, 30);
        $nip = $this->teks($row['nip'] ?? null, 30);
        $identitas = $nim ?: $nip;

        if (blank($identitas)) {
            throw new \RuntimeException('NIM atau NIP wajib diisi, dipakai sebagai kata sandi awal.');
        }

        $data = [
            'name' => trim((string) $row['nama']),
            'email' => strtolower(trim((string) $row['email'])),
            'nim' => $nim,
            'nip' => $nip,
            'phone' => Phone::normalize($row['no_hp'] ?? null),
            'position' => $this->teks($row['jabatan'] ?? null, 120),
            'entry_year' => $this->tahun($row['angkatan'] ?? null),
            'unit_id' => $this->cariUnit($row['kode_unit'] ?? null),
            'is_active' => true,
        ];

        $v = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
        ]);

        if ($v->fails()) {
            throw new \RuntimeException($v->errors()->first());
        }

        $adaSebelumnya = User::where('email', $data['email'])->exists();

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            $adaSebelumnya
                ? $data                                  // akun lama: sandi tidak diganti
                : $data + ['password' => $identitas],    // akun baru: sandi = NIM atau NIP
        );

        $user->syncRoles([$peran]);

        $adaSebelumnya ? $this->diperbarui++ : $this->dibuat++;
    }

    /* ------------------------------ Pembantu ------------------------------ */

    protected function peranAda(string $nama): bool
    {
        return $this->peranCache[$nama] ??= Role::where('name', $nama)->exists();
    }

    protected function cariUnit(?string $kode): ?int
    {
        if (blank($kode)) {
            return null;
        }

        $kode = strtoupper(trim($kode));

        return $this->unitCache[$kode] ??= Unit::where('code', $kode)->value('id');
    }

    protected function teks(mixed $nilai, int $maks): ?string
    {
        $t = trim((string) ($nilai ?? ''));

        return $t === '' ? null : mb_substr($t, 0, $maks);
    }

    protected function tahun(mixed $nilai): ?int
    {
        $angka = (int) preg_replace('/\D+/', '', (string) ($nilai ?? ''));

        return ($angka >= 1990 && $angka <= 2100) ? $angka : null;
    }
}