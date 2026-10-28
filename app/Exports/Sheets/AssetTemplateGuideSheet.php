<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Lembar petunjuk. Tidak punya kolom nama_barang, jadi diabaikan importer. */
class AssetTemplateGuideSheet implements FromArray, WithTitle, WithEvents, WithColumnWidths
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['PETUNJUK PENGISIAN TEMPLAT IMPOR ASET', '', ''],
            ['', '', ''],
            ['Kolom', 'Wajib', 'Isi'],
            ['nama_barang', 'Ya', 'Nama barang'],
            ['kode_kategori', 'Ya', 'Kode kategori yang sudah terdaftar di Data Master'],
            ['kode_unit', 'Ya', 'Kode unit/prodi yang sudah terdaftar'],
            ['kode_ruang', 'Tidak', 'Kode ruang yang sudah terdaftar. Bila diisi tapi tidak ditemukan, baris ditolak'],
            ['kode_vendor', 'Tidak', 'Kode vendor yang sudah terdaftar. Bila diisi tapi tidak ditemukan, baris ditolak'],
            ['merek, model', 'Tidak', ''],
            ['jumlah', 'Tidak', 'Bilangan bulat, default 1'],
            ['kolektif', 'Bila jumlah > 1', 'Ya = satu kode QR untuk semua unit. Tidak = satu kode QR per unit'],
            ['nomor_seri', 'Tidak', 'Hanya untuk jumlah 1'],
            ['tahun_perolehan', 'Tidak', 'Default tahun berjalan'],
            ['tanggal_perolehan', 'Tidak', 'Tanggal Excel atau teks 2026-03-01'],
            ['sumber', 'Tidak', 'Pembelian, Hibah, Bantuan, Produksi Sendiri'],
            ['nilai_per_unit', 'Tidak', 'Harga SATU unit, bukan total'],
            ['kondisi', 'Tidak', 'Baik, Rusak Ringan, Rusak Berat. Default Baik'],
            ['garansi_sampai', 'Tidak', 'Tanggal'],
            ['dapat_dipinjam', 'Tidak', 'Ya atau Tidak'],
            ['peminjam', 'Tidak', 'Semua atau Dosen Saja'],
            ['catatan', 'Tidak', 'Baris berawalan [CONTOH] dilewati saat impor'],
            ['', '', ''],
            ['KOLEKTIF ATAU SATU PER SATU?', '', ''],
            ['Kolektif Ya', '', 'Barang identik yang dipakai bersama dan tidak perlu dilacak per unit: kursi lipat, meja lipat, matras. Hasil: 1 kode aset, 1 stiker QR, jumlah unit tercatat.'],
            ['Kolektif Tidak', '', 'Barang bernilai yang perlu dilacak per unit: laptop, proyektor, mikroskop. Hasil: kode aset dan stiker QR sebanyak jumlah.'],
            ['Alat kecil lab', '', 'Tabung reaksi, pipet, gelas ukur: JANGAN di templat ini. Daftarkan di menu Barang & Alat Massal.'],
            ['', '', ''],
            ['CONTOH PERHITUNGAN', '', ''],
            ['120 kursi @ Rp350.000', '', 'jumlah = 120, kolektif = Ya, nilai_per_unit = 350000 → 1 aset, nilai tercatat Rp42.000.000'],
            ['10 laptop @ Rp9.500.000', '', 'jumlah = 10, kolektif = Tidak, nilai_per_unit = 9500000 → 10 aset, masing-masing Rp9.500.000'],
            ['', '', ''],
            ['SEBELUM MENGUNGGAH', '', ''],
            ['1', '', 'Baris contoh boleh dibiarkan; baris bertanda [CONTOH] tidak diimpor.'],
            ['2', '', 'Jangan mengubah judul kolom di lembar Data Aset.'],
            ['3', '', 'Isi Data Master (unit, gedung, ruang, kategori) terlebih dahulu.'],
            ['4', '', 'Periksa laporan hasil impor. Unggah ulang hanya baris yang gagal agar aset tidak ganda.'],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 16, 'C' => 100];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                foreach (['A1', 'A3:C3', 'A23', 'A28', 'A32'] as $sel) {
                    $s->getStyle($sel)->getFont()->setBold(true);
                }

                $s->getStyle('A1')->getFont()->setSize(13);
                $s->getStyle('C1:C40')->getAlignment()->setWrapText(true);
            },
        ];
    }
}