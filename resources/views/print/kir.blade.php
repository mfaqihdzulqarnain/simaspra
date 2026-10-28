<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 15mm 12mm 15mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #000;
            line-height: 1.4;
        }

        .kop-img {
            width: 100%;
            margin: 0 0 10pt;
        }

        .kop-img img {
            width: 100%;
            height: auto;
            display: block;
        }

        .kop {
            width: 100%;
            border-bottom: 2.5pt solid #000;
            padding-bottom: 6pt;
            margin-bottom: 12pt;
        }

        .kop td {
            vertical-align: middle;
            padding: 0;
        }

        .kop td.logo {
            width: 58pt;
            text-align: center;
        }

        .kop td.logo img {
            width: 50pt;
            height: auto;
        }

        .kop td.spacer {
            width: 58pt;
        }

        .kop .inst {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
        }

        .kop .meta {
            font-size: 7.5pt;
            margin: 2pt 0 0;
        }

        h1.judul {
            font-size: 12pt;
            text-align: center;
            margin: 0 0 3pt;
            text-decoration: underline;
        }

        p.sub {
            text-align: center;
            font-size: 9.5pt;
            margin: 0 0 12pt;
        }

        table.info {
            width: 100%;
            margin: 0 0 10pt;
        }

        table.info td {
            padding: 1pt 0;
            vertical-align: top;
            font-size: 9.5pt;
        }

        table.info td.label {
            width: 100pt;
        }

        table.info td.sep {
            width: 8pt;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 4pt 0 10pt;
        }

        table.data th,
        table.data td {
            border: 0.6pt solid #000;
            padding: 3pt 4pt;
            font-size: 8.5pt;
        }

        table.data th {
            background: #EFEFEF;
            font-weight: bold;
            text-align: center;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data td.c {
            text-align: center;
        }

        table.data td.r {
            text-align: right;
        }

        table.ttd {
            width: 100%;
            margin-top: 16pt;
            table-layout: fixed;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        table.ttd td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 9.5pt;
            padding: 0 8pt;
        }

        .ttd-kota {
            margin: 0 0 2pt;
        }

        .ttd-judul {
            margin: 0;
            height: 13pt;
        }

        .ttd-jabatan {
            margin: 0;
            font-size: 9pt;
            height: 22pt;
        }

        .ruang-ttd {
            height: 44pt;
        }

        .nama-ttd {
            font-weight: bold;
            text-decoration: underline;
        }

        .nip-ttd {
            display: block;
            font-size: 9pt;
            height: 13pt;
        }

        .catatan {
            font-size: 8pt;
            color: #333;
            margin-top: 10pt;
            font-style: italic;
        }
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
                        @if ($phone)
                            Telp. {{ $phone }}
                        @endif
                        @if ($email)
                            · {{ $email }}
                        @endif
                        @if ($website)
                            · {{ $website }}
                        @endif
                    </p>
                </td>
                @if ($logo)
                    <td class="spacer"></td>
                @endif
            </tr>
        </table>
    @endif

    <h1 class="judul">KARTU INVENTARIS RUANGAN</h1>
    <p class="sub">{{ $room->name }} — {{ $room->code }}</p>

    <table class="info">
        <tr>
            <td class="label">Gedung</td>
            <td class="sep">:</td>
            <td>{{ $room->building?->name ?? '—' }} -- Lantai {{ $room->floor }}</td>
        </tr>
        <tr>
            <td class="label">Unit Pengelola</td>
            <td class="sep">:</td>
            <td>{{ $room->unit?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Penanggung Jawab</td>
            <td class="sep">:</td>
            <td>{{ $room->picUser?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Jumlah Aset</td>
            <td class="sep">:</td>
            <td>{{ $assets->count() }} unit</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width:20pt">No</th>
                <th style="width:76pt">Kode Aset</th>
                <th>Nama Barang</th>
                <th style="width:66pt">Kategori</th>
                <th style="width:34pt">Thn</th>
                <th style="width:52pt">Kondisi</th>
                <th style="width:62pt">Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assets as $i => $asset)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="c">{{ $asset->code }}</td>
                    <td>
                        {{ $asset->name }}
                        @if ($asset->brand || $asset->model)
                            <br><span style="font-size:7.5pt">{{ trim($asset->brand . ' ' . $asset->model) }}</span>
                        @endif
                    </td>
                    <td>{{ $asset->category?->name ?? '—' }}</td>
                    <td class="c">{{ $asset->year_acquired }}</td>
                    <td class="c">{{ $asset->condition->getLabel() }}</td>
                    <td class="r">{{ number_format((float) $asset->acquisition_value, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="c" style="padding:12pt">Belum ada aset tercatat di ruangan ini.</td>
                </tr>
            @endforelse

            @if ($assets->isNotEmpty())
                <tr>
                    <td colspan="6" class="r"><strong>Total Nilai</strong></td>
                    <td class="r"><strong>{{ number_format((float) $total, 0, ',', '.') }}</strong></td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="catatan">
        Kartu ini dicetak pada {{ now()->locale('id')->translatedFormat('d F Y, H:i') }} WIB.
        Perubahan setelah tanggal tersebut belum tercantum. Laporkan ketidaksesuaian
        kepada Bagian Sarana dan Prasarana.
    </p>

    <table class="ttd">
        <tr>
            <td>
                <p class="ttd-kota" style="text-align:center">&nbsp;</p>
                <p class="ttd-judul" style="text-align:center">Penanggung Jawab Ruangan,</p>
                <p class="ttd-jabatan" style="text-align:center">{{ $room->picUser?->position ?? '' }}</p>
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $room->picUser?->name ?? '(...................................)' }}</span>
                <span class="nip-ttd">{{ $room->picUser?->nip ? 'NIP/NIDN. ' . $room->picUser->nip : '' }}</span>
            </td>
            <td>
                <p class="ttd-kota" style="text-align:center">Pontianak,
                    {{ now()->locale('id')->translatedFormat('d F Y') }}</p>
                <p class="ttd-judul" style="text-align:center">Mengetahui,</p>
                <p class="ttd-jabatan" style="text-align:center">Ka. Aset &amp; Sarana Prasarana</p>
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $signatory['name'] ?? '(...................................)' }}</span>
                <span class="nip-ttd">{{ $signatory['nip'] ?? null ? 'NIP/NIDN. ' . $signatory['nip'] : '' }}</span>
            </td>
        </tr>
    </table>

</body>

</html>
