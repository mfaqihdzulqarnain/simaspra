<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function headings(): array
    {
        return [
            'nama',
            'email',
            'peran',
            'nim',
            'nip',
            'kode_unit',
            'jabatan',
            'no_hp',
            'angkatan',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Siti Aminah',
                'siti.aminah@student.polita.ac.id',
                'mahasiswa',
                '2026010001',
                '',
                'D3KEB',
                '',
                '081234567890',
                2026,
            ],
            [
                'Dr. Ahmad Fauzi, M.Kes.',
                'ahmad.fauzi@polita.ac.id',
                'dosen',
                '',
                '198501012010011001',
                'D3KEB',
                'Dosen Tetap',
                '081298765432',
                '',
            ],
            [
                'Rina Lestari, A.Md.',
                'rina.lestari@polita.ac.id',
                'laboran',
                '',
                '199203152015032001',
                'D3TLM',
                'Laboran Lab Kimia',
                '081377788899',
                '',
            ],
            [
                'CONTOH — hapus tiga baris di atas sebelum mengimpor',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}