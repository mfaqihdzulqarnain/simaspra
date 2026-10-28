<?php

namespace App\Imports;

use App\Enums\AcquisitionSource;
use App\Enums\AssetCondition;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Room;
use App\Models\Unit;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AssetImport implements ToCollection, WithHeadingRow
{
    public int $berhasil = 0;

    public array $gagal = [];

    /** Cache lookup agar tidak query berulang */
    protected array $unitCache = [];

    protected array $kategoriCache = [];

    protected array $ruangCache = [];

    protected array $vendorCache = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $baris = $i + 2; // baris 1 adalah judul kolom

            if (blank($row['nama_barang'] ?? null)) {
                continue;
            }

            // Baris contoh dari templat tidak diimpor
            if ($this->barisContoh($row)) {
                $this->catatan[] = "Baris {$baris}: baris contoh dilewati.";

                continue;
            }

            try {
                $this->simpan($row, $baris);
            } catch (\Throwable $e) {
                $this->gagal[] = "Baris {$baris}: ".$e->getMessage();
            }
        }
    }

    protected function simpan($row, int $baris): void
    {
        $unit = $this->cariUnit($row['kode_unit'] ?? null);

        if (! $unit) {
            throw new \RuntimeException('Kode unit "'.($row['kode_unit'] ?? '').'" tidak ditemukan.');
        }

        $kategori = $this->cariKategori($row['kode_kategori'] ?? null);

        if (! $kategori) {
            throw new \RuntimeException('Kode kategori "'.($row['kode_kategori'] ?? '').'" tidak ditemukan.');
        }

        $jumlah = $this->jumlah($row['jumlah'] ?? null);

        // Kolektif wajib dipilih tegas bila jumlah lebih dari 1, agar 120 kursi
        // tidak diam-diam menjadi 120 kode aset (atau sebaliknya)
        $pilihanKolektif = $this->pilihanKolektif($row['kolektif'] ?? null);

        if ($jumlah > 1 && $pilihanKolektif === null) {
            throw new \RuntimeException('Jumlah '.$jumlah.', kolom kolektif wajib diisi Ya (1 kode untuk semua unit) '
                .'atau Tidak (1 kode per unit).');
        }

        if ($jumlah === 1 && $pilihanKolektif === true) {
            $this->catatan[] = "Baris {$baris}: kolektif Ya diabaikan karena jumlah 1.";
        }

        $kolektif = $jumlah > 1 && $pilihanKolektif === true;

        if (! $kolektif && $jumlah > 200) {
            throw new \RuntimeException('Jumlah '.$jumlah.' terlalu besar. Maksimal 200 per baris, '
                .'atau isi kolom kolektif "Ya" bila barangnya dicatat dengan satu kode.');
        }

        if ($kolektif && $jumlah > 100000) {
            throw new \RuntimeException('Jumlah '.$jumlah.' terlalu besar untuk satu aset kolektif.');
        }

        $seri = $row['nomor_seri'] ?? null;

        // Nomor seri hanya bermakna untuk satu unit
        if ($jumlah > 1 && filled($seri)) {
            $seri = null;
            $this->catatan[] = "Baris {$baris}: nomor seri diabaikan karena jumlah lebih dari satu.";
        }

        // Judul baru "nilai_per_unit"; judul lama "nilai_perolehan" tetap diterima
        $nilaiPerUnit = $this->angka($row['nilai_per_unit'] ?? $row['nilai_perolehan'] ?? 0);

        $ruang = $this->cariRuang($row['kode_ruang'] ?? null);

        if (filled($row['kode_ruang'] ?? null) && ! $ruang) {
            throw new \RuntimeException('Kode ruang "'.$row['kode_ruang'].'" tidak ditemukan.');
        }

        $vendor = $this->cariVendor($row['kode_vendor'] ?? null);

        if (filled($row['kode_vendor'] ?? null) && ! $vendor) {
            throw new \RuntimeException('Kode vendor "'.$row['kode_vendor'].'" tidak ditemukan.');
        }

        $dasar = [
            'name' => trim($row['nama_barang']),
            'asset_category_id' => $kategori,
            'unit_id' => $unit,
            'room_id' => $ruang,
            'vendor_id' => $vendor,
            'brand' => $row['merek'] ?? null,
            'model' => $row['model'] ?? null,
            'serial_number' => $seri,
            'year_acquired' => (int) ($row['tahun_perolehan'] ?? now()->format('Y')),
            'acquisition_date' => $this->tanggal($row['tanggal_perolehan'] ?? null),
            'acquisition_source' => $this->sumber($row['sumber'] ?? null),
            'acquisition_value' => $nilaiPerUnit,
            'condition' => $this->kondisi($row['kondisi'] ?? null),
            'warranty_until' => $this->tanggal($row['garansi_sampai'] ?? null),
            'is_loanable' => $this->boolean($row['dapat_dipinjam'] ?? null),
            'loanable_by' => $this->peminjam($row['peminjam'] ?? null),
            'notes' => $row['catatan'] ?? null,
            'created_by' => Auth::id(),
        ];

        if ($kolektif) {
            // Satu kode untuk seluruh unit; nilai perolehan disimpan sebagai total
            $total = round($nilaiPerUnit * $jumlah, 2);

            Asset::create([
                ...$dasar,
                'quantity' => $jumlah,
                'acquisition_value' => $total,
            ]);

            // Ringkasan agar salah isi (total diisi sebagai nilai per unit) mudah terlihat
            $this->catatan[] = "Baris {$baris}: {$dasar['name']} kolektif {$jumlah} unit × "
                .$this->rupiah($nilaiPerUnit).' = '.$this->rupiah($total).'.';

            $this->berhasil++;

            return;
        }

        // Satu baris menghasilkan sebanyak jumlah unit, masing-masing berkode sendiri
        for ($i = 0; $i < $jumlah; $i++) {
            Asset::create($dasar);
            $this->berhasil++;
        }
    }

    /** Peringatan yang tidak menggagalkan impor */
    public array $catatan = [];

    protected function jumlah(mixed $nilai): int
    {
        if (blank($nilai)) {
            return 1;
        }

        // Angka Excel (120 atau 120.0) dibulatkan; teks "1.200" dibaca 1200
        if (is_int($nilai) || is_float($nilai)) {
            $n = (int) round($nilai);
        } else {
            $n = (int) preg_replace('/\D+/', '', (string) $nilai);
        }

        if ($n < 1) {
            throw new \RuntimeException('Jumlah "'.$nilai.'" tidak valid. Isi bilangan bulat minimal 1.');
        }

        return $n;
    }

    /**
     * Pilihan kolom kolektif: true (Ya), false (Tidak), atau null bila kosong.
     * Isian lain ditolak agar salah ketik tidak diam-diam dianggap Tidak.
     */
    protected function pilihanKolektif(mixed $nilai): ?bool
    {
        $v = strtolower(trim((string) ($nilai ?? '')));

        return match (true) {
            $v === '' => null,
            in_array($v, ['ya', 'y', 'iya', '1', 'true', 'benar', 'kolektif'], true) => true,
            in_array($v, ['tidak', 't', 'tdk', 'no', 'n', '0', 'false', 'salah'], true) => false,
            default => throw new \RuntimeException('Kolom kolektif "'.$nilai.'" tidak dikenal. Isi Ya atau Tidak.'),
        };
    }

    /** Baris contoh templat: catatan berawalan [CONTOH], atau nama berawalan CONTOH */
    protected function barisContoh($row): bool
    {
        return str_starts_with(strtoupper(trim((string) ($row['catatan'] ?? ''))), '[CONTOH]')
            || str_starts_with(strtoupper(trim((string) ($row['nama_barang'] ?? ''))), 'CONTOH');
    }

    protected function rupiah(float $nilai): string
    {
        return 'Rp'.number_format($nilai, 0, ',', '.');
    }

    protected function peminjam(?string $nilai): string
    {
        return match (strtolower(trim($nilai ?? ''))) {
            'dosen saja', 'dosen_saja', 'dosen' => 'dosen_saja',
            default => 'semua',
        };
    }

    /* ------------------------------ Lookup ------------------------------ */

    protected function cariUnit(?string $kode): ?int
    {
        if (blank($kode)) {
            return null;
        }

        $kode = strtoupper(trim($kode));

        return $this->unitCache[$kode] ??= Unit::where('code', $kode)->value('id');
    }

    protected function cariKategori(?string $kode): ?int
    {
        if (blank($kode)) {
            return null;
        }

        $kode = strtoupper(trim($kode));

        return $this->kategoriCache[$kode] ??= AssetCategory::where('code', $kode)->value('id');
    }

    protected function cariRuang(?string $kode): ?int
    {
        if (blank($kode)) {
            return null;
        }

        $kode = strtoupper(trim($kode));

        return $this->ruangCache[$kode] ??= Room::where('code', $kode)->value('id');
    }

    protected function cariVendor(?string $kode): ?int
    {
        if (blank($kode)) {
            return null;
        }

        $kode = strtoupper(trim($kode));

        return $this->vendorCache[$kode] ??= Vendor::where('code', $kode)->value('id');
    }

    /* ---------------------------- Konversi ---------------------------- */

    protected function kondisi(?string $nilai): AssetCondition
    {
        return match (strtolower(trim($nilai ?? ''))) {
            'rusak ringan', 'rusak_ringan', 'rr' => AssetCondition::RusakRingan,
            'rusak berat', 'rusak_berat', 'rb' => AssetCondition::RusakBerat,
            default => AssetCondition::Baik,
        };
    }

    protected function sumber(?string $nilai): AcquisitionSource
    {
        return match (strtolower(trim($nilai ?? ''))) {
            'hibah' => AcquisitionSource::Hibah,
            'bantuan', 'donasi' => AcquisitionSource::Bantuan,
            'produksi sendiri', 'produksi_sendiri' => AcquisitionSource::ProduksiSendiri,
            default => AcquisitionSource::Pembelian,
        };
    }

    protected function angka(mixed $nilai): float
    {
        if (blank($nilai)) {
            return 0;
        }

        // Sel angka Excel dibaca apa adanya (titik di sini adalah desimal, bukan ribuan)
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        // Terima "9.500.000" maupun "9500000"
        return (float) preg_replace('/[^0-9.]/', '', str_replace('.', '', (string) $nilai));
    }

    protected function tanggal(mixed $nilai): ?string
    {
        if (blank($nilai)) {
            return null;
        }

        try {
            // Excel menyimpan tanggal sebagai angka serial
            if (is_numeric($nilai)) {
                return Date::excelToDateTimeObject($nilai)
                    ->format('Y-m-d');
            }

            return Carbon::parse($nilai)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function boolean(mixed $nilai): bool
    {
        return in_array(strtolower(trim((string) $nilai)), ['ya', 'y', '1', 'true', 'benar'], true);
    }
}
