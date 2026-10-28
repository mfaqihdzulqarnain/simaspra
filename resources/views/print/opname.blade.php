<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
        <style>
        @page { size: A4 portrait; margin: 8mm 18mm 15mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #000; line-height: 1.45; }

        /* Kop gambar */
        .kop-img { width: 100%; margin: 0 0 10pt; }
        .kop-img img { width: 100%; height: auto; display: block; }

        /* Kop rakitan */
        .kop { width: 100%; border-bottom: 2.5pt solid #000; padding-bottom: 6pt; margin-bottom: 12pt; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop td.logo { width: 62pt; text-align: center; }
        .kop td.logo img { width: 54pt; height: auto; }
        .kop td.spacer { width: 62pt; }
        .kop .inst { font-size: 14pt; font-weight: bold; margin: 0; }
        .kop .meta { font-size: 8pt; margin: 2pt 0 0; }

        h1.judul { font-size: 12pt; text-align: center; margin: 0 0 2pt; text-decoration: underline; }
        p.nomor { text-align: center; font-size: 10pt; margin: 0 0 12pt; }

        p { margin: 0 0 6pt; }
        p.isi { text-align: justify; }

        table.pihak { width: 100%; margin: 0 0 8pt; }
        table.pihak td { padding: 0.5pt 0; vertical-align: top; font-size: 10pt; }
        table.pihak td.label { width: 100pt; }
        table.pihak td.sep { width: 10pt; }

        table.ringkas { width: 100%; border-collapse: collapse; margin: 6pt 0 10pt; }
        table.ringkas td { border: 0.6pt solid #000; padding: 3pt 6pt; font-size: 9.5pt; }
        table.ringkas td.n { text-align: right; width: 60pt; font-weight: bold; }

        table.selisih { width: 100%; border-collapse: collapse; margin: 5pt 0 10pt; }
        table.selisih th, table.selisih td { border: 0.6pt solid #000; padding: 3pt 5pt; font-size: 8.5pt; }
        table.selisih th { background: #EFEFEF; font-weight: bold; text-align: center; }
        table.selisih td.c { text-align: center; }

        table.ttd { width: 100%; margin-top: 14pt; table-layout: fixed; border-collapse: collapse; page-break-inside: avoid; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; font-size: 10pt; padding: 0 8pt; }
        .ttd-kota { margin: 0 0 2pt; }
        .ttd-judul { margin: 0; height: 14pt; }
        .ttd-jabatan { margin: 0; font-size: 9.5pt; height: 22pt; }
        .ruang-ttd { height: 48pt; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
        .nip-ttd { display: block; font-size: 9.5pt; height: 14pt; }
        table.selisih thead { display: table-header-group; }
    </style>
</head>
<body>

@if ($useLetterhead && $letterhead)
    <div class="kop-img"><img src="{{ $letterhead }}" alt=""></div>
@else
    <table class="kop">
        <tr>
            @if ($logo)
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td style="text-align:center">
                <p class="inst" style="text-align:center">{{ strtoupper($institution) }}</p>
                @if ($address)
                    <p class="meta" style="text-align:center">{{ $address }}</p>
                @endif
                <p class="meta" style="text-align:center">
                    @if ($phone) Telp. {{ $phone }} @endif
                    @if ($email) · {{ $email }} @endif
                    @if ($website) · {{ $website }} @endif
                </p>
            </td>
            @if ($logo)
                <td class="spacer"></td>
            @endif
        </tr>
    </table>
@endif

<h1 class="judul">BERITA ACARA INVENTARISASI ASET</h1>
<p class="nomor">Nomor: {{ $opname->code }}</p>

<p class="isi">
    Pada hari ini {{ now()->locale('id')->translatedFormat('l') }},
    tanggal {{ now()->locale('id')->translatedFormat('d F Y') }},
    telah dilaksanakan kegiatan inventarisasi aset dengan rincian sebagai berikut:
</p>

<table class="pihak">
    <tr><td class="label">Nama Kegiatan</td><td class="sep">:</td><td>{{ $opname->name }}</td></tr>
    <tr>
        <td class="label">Periode</td><td class="sep">:</td>
        <td>
            {{ $opname->period_start->locale('id')->translatedFormat('d F Y') }}
            s.d. {{ $opname->period_end->locale('id')->translatedFormat('d F Y') }}
        </td>
    </tr>
    <tr><td class="label">Cakupan</td><td class="sep">:</td>
        <td>{{ collect([$opname->unit?->name, $opname->building?->name])->filter()->implode(' · ') ?: 'Seluruh aset kampus' }}</td>
    </tr>
    <tr><td class="label">Pelaksana</td><td class="sep">:</td><td>{{ $opname->creator?->name ?? '—' }}</td></tr>
</table>

<p class="isi">Hasil inventarisasi:</p>

<table class="ringkas">
    <tr><td>Jumlah aset dalam daftar periksa</td><td class="n">{{ $report['total'] }}</td></tr>
    <tr><td>Telah diperiksa</td><td class="n">{{ $report['diperiksa'] }}</td></tr>
    <tr><td>Ditemukan sesuai catatan</td><td class="n">{{ $report['ditemukan'] }}</td></tr>
    <tr><td>Tidak ditemukan</td><td class="n">{{ $report['tidak_ditemukan'] }}</td></tr>
    <tr><td>Ditemukan di lokasi berbeda</td><td class="n">{{ $report['beda_lokasi'] }}</td></tr>
    <tr><td>Kondisi berbeda dari catatan</td><td class="n">{{ $report['beda_kondisi'] }}</td></tr>
    <tr><td>Jumlah unit aset kolektif berbeda</td><td class="n">{{ $report['beda_jumlah'] ?? 0 }}</td></tr>
    <tr><td>Ada di lapangan tetapi belum tercatat</td><td class="n">{{ $report['tidak_tercatat'] }}</td></tr>
</table>

@if ($report['selisih']->isNotEmpty())
    <p class="isi"><strong>Rincian selisih yang perlu ditindaklanjuti:</strong></p>

    <table class="selisih">
        <thead>
            <tr>
                <th style="width:20pt">No</th>
                <th style="width:70pt">Kode Aset</th>
                <th>Nama Barang</th>
                <th style="width:70pt">Lokasi Tercatat</th>
                <th style="width:70pt">Lokasi Ditemukan</th>
                <th style="width:52pt">Jumlah</th>
                <th style="width:70pt">Hasil</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['selisih'] as $i => $item)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $item->asset?->code ?? '—' }}</td>
                    <td>{{ $item->display_name }}</td>
                    <td>{{ $item->expectedRoom?->name ?? '—' }}</td>
                    <td>{{ $item->foundRoom?->name ?? '—' }}</td>
                    <td class="c">{{ $item->quantity_label ?? '—' }}</td>
                    <td class="c">
                        {{ $item->status->getLabel() }}
                        @if ($item->hasQuantityDifference() && $item->quantity_follow_up)
                            <br><span style="font-size:7pt">{{ $item->quantity_follow_up->getLabel() }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="isi">Tidak ditemukan selisih. Seluruh aset sesuai dengan catatan.</p>
@endif

<p class="isi">
    Demikian berita acara ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
</p>

<table class="ttd">
    <tr>
        <td>
            <p class="ttd-kota" style="text-align:center">&nbsp;</p>
            <p class="ttd-judul" style="text-align:center">Mengetahui,</p>
            <p class="ttd-jabatan" style="text-align:center">Wakil Direktur</p>
            <div class="ruang-ttd"></div>
            <span class="nama-ttd">{{ $wadir['name'] ?? '(...................................)' }}</span>
            <span class="nip-ttd">{{ ($wadir['nip'] ?? null) ? 'NIP/NIDN. ' . $wadir['nip'] : '' }}</span>
        </td>
        <td>
            <p class="ttd-kota" style="text-align:center">Pontianak, {{ now()->locale('id')->translatedFormat('d F Y') }}</p>
            <p class="ttd-judul" style="text-align:center">Pelaksana,</p>
            <p class="ttd-jabatan" style="text-align:center">{{ $opname->creator?->position ?? 'Ka. Aset & Sarana Prasarana' }}</p>
            <div class="ruang-ttd"></div>
            <span class="nama-ttd">{{ $opname->creator?->name ?? $signatory['name'] ?? '(...................................)' }}</span>
            <span class="nip-ttd">{{ ($opname->creator?->nip ?? $signatory['nip'] ?? null) ? 'NIP/NIDN. ' . ($opname->creator?->nip ?? $signatory['nip']) : '' }}</span>
        </td>
    </tr>
</table>

</body>
</html>