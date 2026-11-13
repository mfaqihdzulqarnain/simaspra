@php
    $rupiah = fn ($v) => number_format((float) $v, 0, ',', '.');
    // Kolom: No, Kode Aset, Nama Barang, Thn, Kondisi/Jml, (Nilai)
    $kolomAset = $denganNilai ? 6 : 5;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 8mm 15mm 12mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #000; line-height: 1.4; }

        .kop-img { width: 100%; margin: 0 0 10pt; }
        .kop-img img { width: 100%; height: auto; display: block; }
        .kop { width: 100%; border-bottom: 2.5pt solid #000; padding-bottom: 6pt; margin-bottom: 12pt; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop td.logo { width: 58pt; text-align: center; }
        .kop td.logo img { width: 50pt; height: auto; }
        .kop .inst { font-size: 13pt; font-weight: bold; margin: 0; text-align: center; }
        .kop .meta { font-size: 7.5pt; margin: 2pt 0 0; text-align: center; }

        h1 { font-size: 13pt; text-align: center; margin: 0 0 2pt; text-decoration: underline; letter-spacing: .5pt; }
        p.sub { text-align: center; font-size: 10pt; margin: 0 0 12pt; }

        table.info { width: 100%; margin: 0 0 10pt; border-collapse: collapse; }
        table.info td { padding: 1.5pt 0; vertical-align: top; }
        table.info td.label { width: 110pt; }
        table.info td.sep { width: 10pt; }

        h2 { font-size: 10pt; margin: 14pt 0 4pt; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: .6pt solid #000; padding: 3pt 5pt; font-size: 8.5pt; vertical-align: top; }
        table.data th { background: #EFEFEF; text-align: center; }
        table.data td.c { text-align: center; }
        table.data td.r { text-align: right; }
        table.data tfoot td { font-weight: bold; background: #F7F7F7; }

        .kategori { font-size: 7.5pt; color: #444; }
        .merek { font-size: 7.5pt; color: #444; }
        .jml { font-weight: bold; }

        p.catatan { font-size: 8pt; font-style: italic; color: #333; margin: 8pt 0 0; }

        table.ttd { width: 100%; margin-top: 20pt; table-layout: fixed; border-collapse: collapse; page-break-inside: avoid; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; padding: 0 8pt; }
        .ttd-kota { margin: 0 0 2pt; }
        .ttd-judul { margin: 0; height: 13pt; }
        .ttd-jabatan { margin: 0; font-size: 9pt; height: 22pt; }
        .ruang-ttd { height: 48pt; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
        .nip-ttd { display: block; font-size: 9pt; height: 13pt; }
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
                <td>
                    <p class="inst">{{ strtoupper($institution) }}</p>
                    @if ($address)
                        <p class="meta">{{ $address }}</p>
                    @endif
                    <p class="meta">
                        @if ($phone) Telp. {{ $phone }} @endif
                        @if ($email) · {{ $email }} @endif
                    </p>
                </td>
                @if ($logo)
                    <td class="logo"></td>
                @endif
            </tr>
        </table>
    @endif

    <h1>KARTU INVENTARIS RUANGAN</h1>
    <p class="sub">{{ $room->name }} — {{ $room->code }}</p>

    <table class="info">
        <tr>
            <td class="label">Gedung</td>
            <td class="sep">:</td>
            <td>
                {{ $room->building?->name ?? '—' }}
                @if ($room->floor) — Lantai {{ $room->floor }} @endif
            </td>
        </tr>
        <tr>
            <td class="label">Unit Pengelola</td>
            <td class="sep">:</td>
            <td>{{ $room->unit?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Laboratorium</td>
            <td class="sep">:</td>
            <td>{{ $room->laboratory?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Penanggung Jawab</td>
            <td class="sep">:</td>
            <td>{{ $room->picUser?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Jumlah Aset</td>
            <td class="sep">:</td>
            <td>
                {{ $assets->count() }} aset
                @if ($totalUnit > $assets->count()) ({{ $totalUnit }} unit) @endif
            </td>
        </tr>
    </table>

    {{-- ===================== Aset ber-kode ===================== --}}
    <table class="data">
        <thead>
            <tr>
                <th style="width:22pt">No</th>
                <th style="width:100pt">Kode Aset</th>
                <th>Nama Barang</th>
                <th style="width:34pt">Thn</th>
                <th style="width:62pt">Kondisi</th>
                @if ($denganNilai)
                    <th style="width:82pt">Nilai (Rp)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($assets as $i => $a)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>
                        {{ $a->code }}
                        <br><span class="kategori">({{ $a->category?->name ?? 'Tanpa kategori' }})</span>
                    </td>
                    <td>
                        {{ $a->name }}
                        @if ($a->brand || $a->model)
                            <br><span class="merek">{{ collect([$a->brand, $a->model])->filter()->implode(' ') }}</span>
                        @endif
                    </td>
                    <td class="c">{{ $a->year_acquired ?? '—' }}</td>
                    <td class="c">
                        {{ $a->condition?->getLabel() ?? '—' }}
                        <br><span class="jml">{{ max(1, (int) $a->quantity) }} unit</span>
                    </td>
                    @if ($denganNilai)
                        <td class="r">{{ $rupiah($a->acquisition_value) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $kolomAset }}" class="c">Belum ada aset tercatat di ruangan ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($assets->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="4" class="r">Total Unit</td>
                    <td class="c">{{ $totalUnit }} unit</td>
                    @if ($denganNilai)
                        <td class="r">{{ $rupiah($totalNilai) }}</td>
                    @endif
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- ===================== Barang & alat massal ===================== --}}
    @if ($consumables->isNotEmpty())
        <h2>Barang &amp; Alat Massal</h2>
        <table class="data">
            <thead>
                <tr>
                    <th style="width:22pt">No</th>
                    <th style="width:100pt">Kode</th>
                    <th>Nama Barang</th>
                    <th style="width:62pt">Stok</th>
                    @if ($denganNilai)
                        <th style="width:70pt">Harga Satuan</th>
                        <th style="width:82pt">Nilai (Rp)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($consumables as $i => $c)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td>
                            {{ $c->code }}
                            <br><span class="kategori">({{ $c->type?->getLabel() ?? '—' }})</span>
                        </td>
                        <td>{{ $c->name }}</td>
                        <td class="c jml">{{ $c->current_stock }} {{ $c->unit_of_measure }}</td>
                        @if ($denganNilai)
                            <td class="r">{{ $c->unit_price ? $rupiah($c->unit_price) : '—' }}</td>
                            <td class="r">
                                {{ $c->unit_price ? $rupiah((float) $c->unit_price * (float) $c->current_stock) : '—' }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="catatan">
        Kartu ini dicetak pada {{ now()->locale('id')->translatedFormat('d F Y, H.i') }} WIB.
        Perubahan setelah tanggal tersebut belum tercantum.
        @unless ($denganNilai)
            Nilai perolehan tidak ditampilkan pada cetakan ini.
        @endunless
        Laporkan ketidaksesuaian kepada Bagian Sarana dan Prasarana.
    </p>

    <table class="ttd">
        <tr>
            <td>
                <p class="ttd-kota">&nbsp;</p>
                <p class="ttd-judul">Penanggung Jawab Ruangan,</p>
                <p class="ttd-jabatan">{{ $room->picUser?->position }}</p>
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $room->picUser?->name ?? '(...................................)' }}</span>
                <span class="nip-ttd"></span>
            </td>
            <td>
                <p class="ttd-kota">Pontianak, {{ now()->locale('id')->translatedFormat('d F Y') }}</p>
                <p class="ttd-judul">Mengetahui,</p>
                <p class="ttd-jabatan">Ka. Aset &amp; Sarana Prasarana</p>
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $signatory['name'] ?? '(...................................)' }}</span>
                <span class="nip-ttd">{{ ($signatory['nip'] ?? null) ? 'NIP/NIDN. '.$signatory['nip'] : '' }}</span>
            </td>
        </tr>
    </table>

</body>

</html>