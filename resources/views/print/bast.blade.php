{{--
    Templat berita acara serba guna.
    Dipakai untuk BAST, BAPP, BAPK, BAPB, dan BAM.
    Judul dan isi menyesuaikan HandoverType.
--}}
@php
    use App\Enums\HandoverType;

    $ref = $handover->reference;

    $items = $handover->items ?? collect();
    $massal = $ref?->consumableItems ?? collect();

    $adaAset = $items->isNotEmpty();
    $adaMassal = $massal->isNotEmpty();

    // Kolom nilai hanya relevan saat penyaluran hasil pengadaan
    $pakaiNilai = $handover->type === HandoverType::Penyaluran;

    // Nilai sebanding jumlah: aset kolektif yang dipinjam sebagian
    // tidak tercetak dengan nilai seluruh unitnya
    $nilaiBaris = fn ($item) => $item->asset
        ? $item->asset->unitValue() * max(1, (int) $item->quantity)
        : 0;

    $judul = match ($handover->type) {
        HandoverType::Penyaluran => 'BERITA ACARA SERAH TERIMA BARANG',
        HandoverType::Peminjaman => 'BERITA ACARA PEMINJAMAN BARANG',
        HandoverType::Pengembalian => 'BERITA ACARA PENGEMBALIAN BARANG',
        HandoverType::Penghapusan => 'BERITA ACARA PENGHAPUSAN BARANG',
        HandoverType::Mutasi => 'BERITA ACARA MUTASI BARANG',
        default => 'BERITA ACARA SERAH TERIMA',
    };

    $kalimatPenutup = match ($handover->type) {
        HandoverType::Peminjaman => 'Barang yang dipinjam menjadi tanggung jawab peminjam '
            . 'dan wajib dikembalikan dalam keadaan baik sesuai batas waktu yang ditentukan.',
        HandoverType::Pengembalian => 'Barang telah diterima kembali dan diperiksa kondisinya '
            . 'oleh petugas yang berwenang.',
        HandoverType::Penghapusan => 'Barang tersebut telah dikeluarkan dari daftar inventaris '
            . 'sesuai ketentuan yang berlaku.',
        HandoverType::Mutasi => 'Barang tersebut telah berpindah pengelolaan '
            . 'sesuai rincian di atas.',
        default => 'Barang yang telah diserahkan menjadi tanggung jawab unit penerima.',
    };

    $tgl = ($handover->handed_over_at ?? $handover->created_at);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 8mm 18mm 12mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #000; line-height: 1.5; }

        /* Kop surat */
        .kop-img { width: 100%; margin: 0 0 10pt; }
        .kop-img img { width: 100%; height: auto; max-height: 30mm; display: block; }

        .kop { width: 100%; border-bottom: 2.5pt solid #000; padding-bottom: 6pt; margin-bottom: 14pt; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop td.logo { width: 58pt; text-align: center; }
        .kop td.logo img { width: 50pt; height: auto; }
        .kop td.spacer { width: 58pt; }
        .kop .inst { font-size: 13pt; font-weight: bold; margin: 0; }
        .kop .meta { font-size: 7.5pt; margin: 2pt 0 0; }

        /* Judul */
        h1.judul { font-size: 12pt; text-align: center; margin: 0 0 3pt; text-decoration: underline; }
        p.nomor { text-align: center; font-size: 10pt; margin: 0 0 14pt; }

        /* Isi */
        p.isi { text-align: justify; margin: 0 0 8pt; }

        /* Tabel identitas pihak */
        table.pihak { width: 100%; margin: 0 0 10pt; }
        table.pihak td { padding: 1pt 0; vertical-align: top; font-size: 10pt; }
        table.pihak td.label { width: 92pt; padding-left: 14pt; }
        table.pihak td.sep { width: 8pt; }
        table.pihak td.judul-pihak { font-weight: bold; padding-top: 6pt; }

        /* Tabel rincian barang */
        table.data { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt; }
        table.data th, table.data td { border: 0.6pt solid #000; padding: 3pt 4pt; font-size: 8.5pt; }
        table.data th { background: #EFEFEF; font-weight: bold; text-align: center; }
        table.data thead { display: table-header-group; }
        table.data td.c { text-align: center; }
        table.data td.r { text-align: right; }

        p.sub-tabel { font-weight: bold; margin: 10pt 0 2pt; font-size: 9.5pt; }

        /* Tanda tangan */
        table.ttd { width: 100%; margin-top: 18pt; table-layout: fixed;
                    border-collapse: collapse; page-break-inside: avoid; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; font-size: 10pt; padding: 0 8pt; }
        .ttd-kota { margin: 0 0 2pt; }
        .ttd-judul { margin: 0; height: 14pt; }
        .ttd-jabatan { margin: 0; font-size: 9pt; height: 24pt; }
        .ruang-ttd { height: 46pt; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
        .nip-ttd { display: block; font-size: 9pt; height: 13pt; }

        .catatan { font-size: 8pt; color: #333; margin-top: 12pt; font-style: italic; }
    </style>
</head>
<body>

{{-- ============================== KOP SURAT ============================== --}}
@if (!empty($useLetterhead) && !empty($letterhead))
    <div class="kop-img"><img src="{{ $letterhead }}" alt=""></div>
@else
    <table class="kop">
        <tr>
            @if (!empty($logo))
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td style="text-align:center">
                <p class="inst" style="text-align:center">{{ strtoupper($institution ?? '') }}</p>
                @if (!empty($address))
                    <p class="meta" style="text-align:center">{{ $address }}</p>
                @endif
                <p class="meta" style="text-align:center">
                    @if (!empty($phone)) Telp. {{ $phone }} @endif
                    @if (!empty($email)) · {{ $email }} @endif
                    @if (!empty($website)) · {{ $website }} @endif
                </p>
            </td>
            @if (!empty($logo))
                <td class="spacer"></td>
            @endif
        </tr>
    </table>
@endif

{{-- =============================== JUDUL ================================ --}}
<h1 class="judul">{{ $judul }}</h1>
<p class="nomor">Nomor: {{ $handover->number }}</p>

{{-- =============================== PEMBUKA ============================== --}}
<p class="isi">
    Pada hari ini {{ $tgl->locale('id')->translatedFormat('l') }},
    tanggal {{ $tgl->locale('id')->translatedFormat('d F Y') }},
    bertempat di {{ $institution ?? '' }},
    telah dilaksanakan serah terima barang dengan rincian sebagai berikut:
</p>

{{-- ========================== IDENTITAS PIHAK =========================== --}}
<table class="pihak">
    <tr>
        <td class="judul-pihak" colspan="3">Yang Menyerahkan</td>
    </tr>
    <tr>
        <td class="label">Nama</td>
        <td class="sep">:</td>
        <td>{{ $handover->fromUser?->name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Jabatan</td>
        <td class="sep">:</td>
        <td>{{ $handover->fromUser?->position ?? ($signatory['position'] ?? '—') }}</td>
    </tr>

    <tr>
        <td class="judul-pihak" colspan="3">Yang Menerima</td>
    </tr>
    <tr>
        <td class="label">Nama</td>
        <td class="sep">:</td>
        <td>{{ $handover->toUser?->name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Jabatan</td>
        <td class="sep">:</td>
        <td>{{ $handover->toUser?->position ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Unit</td>
        <td class="sep">:</td>
        <td>{{ $handover->unit?->name ?? '—' }}</td>
    </tr>

    {{-- Keterangan tambahan khusus peminjaman --}}
    @if ($ref && $handover->type === HandoverType::Peminjaman)
        <tr>
            <td class="judul-pihak" colspan="3">Keterangan Peminjaman</td>
        </tr>
        @if ($ref->laboratory)
            <tr>
                <td class="label">Laboratorium</td>
                <td class="sep">:</td>
                <td>{{ $ref->laboratory->name }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Keperluan</td>
            <td class="sep">:</td>
            <td>{{ $ref->purpose_type?->getLabel() ?? '—' }}{{ $ref->course_name ? ' — ' . $ref->course_name : '' }}</td>
        </tr>
        <tr>
            <td class="label">Jangka Waktu</td>
            <td class="sep">:</td>
            <td>
                {{ $ref->start_at?->locale('id')->translatedFormat('d F Y, H:i') }}
                s.d. {{ $ref->end_at?->locale('id')->translatedFormat('d F Y, H:i') }}
            </td>
        </tr>
    @endif
</table>

{{-- ========================== RINCIAN BARANG ============================ --}}
<p class="isi">Adapun barang yang diserahterimakan adalah:</p>

{{-- Alat berstiker QR --}}
@if ($adaAset)
    @if ($adaMassal)
        <p class="sub-tabel">A. Alat Berkode</p>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width:24pt">No</th>
                <th style="width:76pt">Kode Aset</th>
                <th>Nama Barang</th>
                <th style="width:70pt">Lokasi</th>
                <th style="width:34pt">Jml</th>
                <th style="width:52pt">Kondisi</th>
                @if ($pakaiNilai)
                    <th style="width:62pt">Nilai (Rp)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $i => $item)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $item->asset?->code ?? '—' }}</td>
                    <td>
                        {{ $item->asset?->name ?? ($item->description ?? '—') }}
                        @if ($item->asset?->brand || $item->asset?->model)
                            <br><span style="font-size:7.5pt">{{ trim(($item->asset->brand ?? '') . ' ' . ($item->asset->model ?? '')) }}</span>
                        @endif
                    </td>
                    <td>{{ $item->asset?->room?->name ?? ($item->asset?->unit?->name ?? '—') }}</td>
                    <td class="c">{{ max(1, (int) $item->quantity) }}</td>
                    <td class="c">
                        {{ ($item->condition ?? $item->asset?->condition)?->getLabel() ?? '—' }}
                        @if (filled($item->note))
                            <br><span style="font-size:7pt">{{ $item->note }}</span>
                        @endif
                    </td>
                    @if ($pakaiNilai)
                        <td class="r">{{ number_format((float) $nilaiBaris($item), 0, ',', '.') }}</td>
                    @endif
                </tr>
            @endforeach

            @if ($pakaiNilai)
                <tr>
                    <td colspan="6" class="r"><strong>Total</strong></td>
                    <td class="r">
                        <strong>{{ number_format((float) $items->sum($nilaiBaris), 0, ',', '.') }}</strong>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
@endif

{{-- Alat massal, tanpa kode individual --}}
@if ($adaMassal)
    @if ($adaAset)
        <p class="sub-tabel">B. Alat Massal</p>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width:24pt">No</th>
                <th>Nama Alat</th>
                <th style="width:70pt">Jumlah</th>
                <th style="width:70pt">Satuan</th>
                @if ($handover->type === HandoverType::Pengembalian)
                    <th style="width:70pt">Kembali</th>
                    <th style="width:70pt">Rusak</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($massal as $i => $item)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $item->consumable?->name ?? '—' }}</td>
                    <td class="c">{{ $item->quantity }}</td>
                    <td class="c">{{ $item->consumable?->unit_of_measure ?? '—' }}</td>
                    @if ($handover->type === HandoverType::Pengembalian)
                        <td class="c">{{ $item->returned_quantity ?? '—' }}</td>
                        <td class="c">{{ $item->damaged_quantity ?: '—' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (! $adaAset && ! $adaMassal)
    <p class="isi"><em>Tidak ada rincian barang.</em></p>
@endif

{{-- Catatan pengembalian yang bermasalah --}}
@if ($handover->type === HandoverType::Pengembalian && $massal->whereNotNull('note')->isNotEmpty())
    <p class="sub-tabel">Catatan Pengembalian</p>
    @foreach ($massal->whereNotNull('note') as $item)
        <p class="isi" style="margin:0 0 3pt;font-size:9pt">
            {{ $item->consumable?->name ?? '—' }}: {{ $item->note }}
        </p>
    @endforeach
@endif

@if (filled($handover->notes))
    <p class="isi" style="margin-top:8pt"><strong>Catatan:</strong> {{ $handover->notes }}</p>
@endif

{{-- =============================== PENUTUP ============================== --}}
<p class="isi" style="margin-top:10pt">
    Demikian berita acara ini dibuat dengan sebenarnya untuk dapat dipergunakan
    sebagaimana mestinya. {{ $kalimatPenutup }}
</p>

{{-- ============================ TANDA TANGAN ============================ --}}
<table class="ttd">
    <tr>
        <td>
            <p class="ttd-kota" style="text-align:center">&nbsp;</p>
            <p class="ttd-judul" style="text-align:center">Yang Menyerahkan,</p>
            <p class="ttd-jabatan" style="text-align:center">
                {{ $handover->fromUser?->position ?? ($signatory['position'] ?? '') }}
            </p>
            <div class="ruang-ttd"></div>
            <span class="nama-ttd">{{ $handover->fromUser?->name ?? '(...................................)' }}</span>
            <span class="nip-ttd">{{ $handover->fromUser?->nip ? 'NIP/NIDN. ' . $handover->fromUser->nip : '' }}</span>
        </td>
        <td>
            <p class="ttd-kota" style="text-align:center">
                Pontianak, {{ $tgl->locale('id')->translatedFormat('d F Y') }}
            </p>
            <p class="ttd-judul" style="text-align:center">Yang Menerima,</p>
            <p class="ttd-jabatan" style="text-align:center">
                {{ $handover->toUser?->position ?? '' }}
            </p>
            <div class="ruang-ttd"></div>
            <span class="nama-ttd">{{ $handover->toUser?->name ?? '(...................................)' }}</span>
            <span class="nip-ttd">{{ $handover->toUser?->identity_number ? 'NIP/NIM. ' . $handover->toUser->identity_number : '' }}</span>
        </td>
    </tr>
</table>

</body>
</html>