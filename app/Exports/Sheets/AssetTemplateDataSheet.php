<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AssetTemplateDataSheet implements FromArray, WithHeadings, WithTitle, WithEvents, WithColumnWidths
{
    /** Baris yang diberi pilihan isian (dropdown dan validasi) */
    protected const BARIS_AKHIR = 2000;

    public function title(): string
    {
        return 'Data Aset';
    }

    /**
     * Urutan kolom A sampai S. Jangan diubah tanpa menyesuaikan huruf kolom di registerEvents().
     * "nilai_per_unit" menggantikan "nilai_perolehan" agar tidak tertukar dengan nilai total;
     * importer tetap menerima judul lama.
     */
    public function headings(): array
    {
        return [
            'nama_barang',        // A
            'kode_kategori',      // B
            'kode_unit',          // C
            'kode_ruang',         // D
            'kode_vendor',        // E
            'merek',              // F
            'model',              // G
            'jumlah',             // H
            'kolektif',           // I
            'nomor_seri',         // J
            'tahun_perolehan',    // K
            'tanggal_perolehan',  // L
            'sumber',             // M
            'nilai_per_unit',     // N
            'kondisi',            // O
            'garansi_sampai',     // P
            'dapat_dipinjam',     // Q
            'peminjam',           // R
            'catatan',            // S
        ];
    }

    /** Baris contoh bertanda [CONTOH] di kolom catatan dilewati importer */
    public function array(): array
    {
        return [
            ['Laptop Lenovo ThinkPad', 'LAP', 'D3KEB', 'GDA-201', '', 'Lenovo', 'ThinkPad E14',
                10, 'Tidak', '', 2026, '2026-01-15', 'Pembelian', 9500000, 'Baik', '2029-01-15', 'Ya', 'Semua',
                '[CONTOH] 10 unit, kolektif Tidak = 10 kode aset dan 10 stiker QR'],
            ['Timbangan Analitik', 'ALT', 'D3TLM', 'TLM-102', '', 'Ohaus', 'PX224',
                1, 'Tidak', 'B938472011', 2026, '2026-02-01', 'Pembelian', 32000000, 'Baik', '2028-02-01', 'Ya', 'Dosen Saja',
                '[CONTOH] 1 unit; nomor seri hanya untuk jumlah 1'],
            ['Kursi Lipat Chitose', 'MBL', 'SARPRAS', 'GUD-01', '', 'Chitose', 'Yamato',
                120, 'Ya', '', 2026, '2026-03-01', 'Pembelian', 350000, 'Baik', '', 'Ya', 'Semua',
                '[CONTOH] 120 unit, kolektif Ya = 1 kode QR; total tercatat 120 × 350.000 = 42.000.000'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, 'B' => 14, 'C' => 12, 'D' => 12, 'E' => 12, 'F' => 14, 'G' => 16,
            'H' => 9, 'I' => 11, 'J' => 16, 'K' => 10, 'L' => 14, 'M' => 16, 'N' => 16,
            'O' => 14, 'P' => 14, 'Q' => 12, 'R' => 13, 'S' => 60,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();
                $akhir = self::BARIS_AKHIR;

                // Judul tebal, biru muda; kolom yang menentukan aset kolektif diberi kuning
                $s->getStyle('A1:S1')->getFont()->setBold(true);
                $s->getStyle('A1:S1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
                $s->getStyle('H1:I1')->getFill()->getStartColor()->setRGB('FDE68A');
                $s->getStyle('N1')->getFill()->getStartColor()->setRGB('FDE68A');
                $s->freezePane('B2');

                // Baris contoh: miring dan abu-abu
                $s->getStyle('A2:S4')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

                // Format angka
                $s->getStyle("N2:N{$akhir}")->getNumberFormat()->setFormatCode('#,##0');
                $s->getStyle("H2:H{$akhir}")->getNumberFormat()->setFormatCode('0');

                // Pilihan isian
                $this->daftar($s, "I2:I{$akhir}", ['Ya', 'Tidak'], 'Kolektif',
                    'Ya: satu kode QR untuk semua unit (kursi, meja). Tidak: satu kode per unit. Wajib diisi bila jumlah lebih dari 1.');
                $this->daftar($s, "M2:M{$akhir}", ['Pembelian', 'Hibah', 'Bantuan', 'Produksi Sendiri'], 'Sumber', null);
                $this->daftar($s, "O2:O{$akhir}", ['Baik', 'Rusak Ringan', 'Rusak Berat'], 'Kondisi', null);
                $this->daftar($s, "Q2:Q{$akhir}", ['Ya', 'Tidak'], 'Dapat dipinjam', null);
                $this->daftar($s, "R2:R{$akhir}", ['Semua', 'Dosen Saja'], 'Peminjam', null);

                $this->angka($s, "H2:H{$akhir}", DataValidation::TYPE_WHOLE, 1, 100000, 'Jumlah',
                    'Bilangan bulat, minimal 1.');
                $this->angka($s, "N2:N{$akhir}", DataValidation::TYPE_DECIMAL, 0, 100000000000, 'Nilai per unit',
                    'Harga SATU unit, bukan total. Untuk 120 kursi @ 350.000, isi 350000.');

                // Komentar di judul kolom penting
                $this->komentar($s, 'H1', 'Jumlah unit identik dalam satu baris.');
                $this->komentar($s, 'I1', "Wajib bila jumlah > 1.\nYa = 1 kode QR untuk semua unit.\nTidak = 1 kode QR per unit (maks. 200 per baris).");
                $this->komentar($s, 'N1', "Harga SATU unit.\nSistem menghitung total = nilai per unit × jumlah.");
            },
        ];
    }

    protected function daftar(Worksheet $s, string $rentang, array $pilihan, string $judul, ?string $petunjuk): void
    {
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle($judul.' tidak valid')
            ->setError('Pilih salah satu: '.implode(', ', $pilihan))
            ->setFormula1('"'.implode(',', $pilihan).'"');

        if ($petunjuk) {
            $v->setShowInputMessage(true)->setPromptTitle($judul)->setPrompt($petunjuk);
        }

        $s->setDataValidation($rentang, $v);
    }

    protected function angka(Worksheet $s, string $rentang, string $tipe, float $min, float $max, string $judul, string $petunjuk): void
    {
        $v = new DataValidation;
        $v->setType($tipe)
            ->setOperator(DataValidation::OPERATOR_BETWEEN)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle($judul.' tidak valid')
            ->setError($petunjuk)
            ->setShowInputMessage(true)
            ->setPromptTitle($judul)
            ->setPrompt($petunjuk)
            ->setFormula1((string) $min)
            ->setFormula2((string) $max);

        $s->setDataValidation($rentang, $v);
    }

    protected function komentar(Worksheet $s, string $sel, string $teks): void
    {
        $k = $s->getComment($sel);
        $k->getText()->createTextRun($teks);
        $k->setWidth('260pt')->setHeight('80pt');
    }
}