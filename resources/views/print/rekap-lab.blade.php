@php
    $tgl = fn ($d) => $d?->locale('id')->translatedFormat('d M Y');
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 8mm 12mm 10mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #000; line-height: 1.4; }

        .kop-img img { width: 100%; height: auto; display: block; margin-bottom: 8pt; }
        .kop { width: 100%; border-bottom: 2.5pt solid #000; padding-bottom: 5pt; margin-bottom: 10pt; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop td.logo { width: 54pt; text-align: center; }
        .kop td.logo img { width: 46pt; height: auto; }
        .kop .inst { font-size: 12pt; font-weight: bold; margin: 0; text-align: center; }
        .kop .meta { font-size: 7.5pt; margin: 2pt 0 0; text-align: center; }

        h1 { font-size: 12pt; text-align: center; margin: 0 0 2pt; letter-spacing: .5pt; }
        p.sub { text-align: center; margin: 0 0 10pt; font-size: 9pt; }

        table.ringkas { width: 100%; border-collapse: collapse; margin: 0 0 10pt; table-layout: fixed; }
        table.ringkas td { border: .6pt solid #000; padding: 4pt; text-align: center; }
        table.ringkas .angka { font-size: 13pt; font-weight: bold; display: block; }
        table.ringkas .ket { font-size: 7.5pt; }

        h2 { font-size: 9.5pt; margin: 10pt 0 4pt; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: .6pt solid #000; padding: 2.5pt 4pt; vertical-align: top; }
        table.data th { background: #EFEFEF; text-align: center; font-size: 8pt; }
        td.c { text-align: center; }
        td.r { text-align: right; }
        .kecil { font-size: 7.5pt; color: #333; }

        table.dua { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.dua > tbody > tr > td { vertical-align: top; padding: 0; }
        table.dua > tbody > tr > td + td { padding-left: 10pt; }

        table.ttd { width: 100%; margin-top: 16pt; table-layout: fixed; page-break-inside: avoid; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; }
        .ruang-ttd { height: 44pt; }
        .nama-ttd { font-weight: bold; text-decoration: underline; }
    </style>
</head>

<body>
    @if ($useLetterhead && $letterhead)
        <div class="kop-img"><img src="{{ $letterhead }}" alt=""></div>
    @else
        <table class="kop">
            <tr>
                @if ($logo)<td class="logo"><img src="{{ $logo }}" alt=""></td>@endif
                <td>
                    <p class="inst">{{ strtoupper($institution) }}</p>
                    @if ($address)<p class="meta">{{ $address }}</p>@endif
                    <p class="meta">
                        @if ($phone) Telp. {{ $phone }} @endif
                        @if ($email) · {{ $email }} @endif
                    </p>
                </td>
                @if ($logo)<td class="logo"></td>@endif
            </tr>
        </table>
    @endif

    <h1>REKAP PEMINJAMAN ALAT LABORATORIUM</h1>
    <p class="sub">
        <strong>{{ $lab->name }}</strong> ({{ $lab->code }}){{ $lab->unit ? ' · '.$lab->unit->name : '' }}<br>
        Periode {{ $periodeLabel }}: {{ $tgl($dari) }} – {{ $tgl($sampai) }}
    </p>

    <table class="ringkas">
        <tr>
            <td><span class="angka">{{ $ringkas['total_pinjaman'] }}</span><span class="ket">Total peminjaman</span></td>
            <td><span class="angka">{{ $ringkas['selesai'] }}</span><span class="ket">Sudah dikembalikan</span></td>
            <td><span class="angka">{{ $ringkas['berjalan'] }}</span><span class="ket">Sedang berjalan (saat dicetak)</span></td>
            <td><span class="angka">{{ $ringkas['terlambat'] }}</span><span class="ket">Kembali terlambat ({{ $ringkas['persen_terlambat'] }}%)</span></td>
            <td><span class="angka">{{ $ringkas['alat_dipinjamkan'] }} / {{ $ringkas['total_alat'] }}</span><span class="ket">Alat dapat dipinjam / total alat</span></td>
        </tr>
    </table>

    <h2>Daftar Peminjaman</h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width:18pt">No</th>
                <th style="width:72pt">Nomor</th>
                <th style="width:110pt">Peminjam</th>
                <th style="width:70pt">Keperluan</th>
                <th style="width:96pt">Jadwal</th>
                <th>Alat</th>
                <th style="width:62pt">Status</th>
                <th style="width:58pt">Dikembalikan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pinjaman as $i => $p)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $p->loan_number }}</td>
                    <td>
                        {{ $p->borrower_name }}
                        @if ($p->borrower_identity)<br><span class="kecil">{{ $p->borrower_identity }}</span>@endif
                    </td>
                    <td>
                        {{ $labelKeperluan($p->purpose_type?->value) }}
                        @if ($p->course_name)<br><span class="kecil">{{ $p->course_name }}</span>@endif
                    </td>
                    <td class="kecil">{{ $p->start_at?->format('d/m/Y H.i') }}<br>s.d. {{ $p->end_at?->format('d/m/Y H.i') }}</td>
                    <td class="kecil">
                        @foreach ($p->items as $item)
                            {{ $item->asset?->code }} {{ $item->asset?->name }}@if ($item->quantity > 1) × {{ $item->quantity }}@endif
                            @if ($item->returned_quantity !== null && $item->return_summary) ({{ $item->return_summary }})@endif<br>
                        @endforeach
                        @foreach ($p->consumableItems as $m)
                            {{ $m->consumable?->name }} × {{ $m->quantity }} {{ $m->consumable?->unit_of_measure }}@if ($m->returned_quantity !== null) (kembali {{ $m->returned_quantity }}@if ($m->damaged_quantity), rusak {{ $m->damaged_quantity }}@endif)@endif<br>
                        @endforeach
                    </td>
                    <td class="c">{{ $p->status?->getLabel() }}</td>
                    <td class="c kecil">{{ $p->returned_at?->format('d/m/Y H.i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="c">Tidak ada peminjaman pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="dua">
        <tr>
            <td>
                <h2>Alat Paling Sering Dipinjam</h2>
                <table class="data">
                    <thead><tr><th style="width:18pt">No</th><th>Alat</th><th style="width:44pt">Kali</th></tr></thead>
                    <tbody>
                        @forelse ($tersering as $i => $t)
                            <tr>
                                <td class="c">{{ $i + 1 }}</td>
                                <td>{{ $t->asset?->code }} — {{ $t->asset?->name }}</td>
                                <td class="c">{{ $t->jumlah }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="c">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td>
                <h2>Peminjam Terlambat</h2>
                <table class="data">
                    <thead><tr><th style="width:18pt">No</th><th>Nama</th><th style="width:44pt">Kali</th></tr></thead>
                    <tbody>
                        @forelse ($terlambat as $i => $t)
                            <tr>
                                <td class="c">{{ $i + 1 }}</td>
                                <td>{{ $t->borrower_name }}@if ($t->borrower_identity) <span class="kecil">({{ $t->borrower_identity }})</span>@endif</td>
                                <td class="c">{{ $t->jumlah }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="c">Tidak ada keterlambatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <h2>Keperluan Peminjaman</h2>
                <table class="data">
                    <thead><tr><th>Keperluan</th><th style="width:44pt">Jumlah</th></tr></thead>
                    <tbody>
                        @forelse ($keperluan as $k)
                            <tr><td>{{ $labelKeperluan($k->purpose_type?->value ?? $k->purpose_type) }}</td><td class="c">{{ $k->jumlah }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="c">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="ttd">
        <tr>
            <td>
                <p style="margin:0">Dicetak oleh,</p>
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $pencetak->name }}</span><br>
                <span class="kecil">{{ now()->locale('id')->translatedFormat('d F Y H.i') }}</span>
            </td>
            <td>
                <p style="margin:0">Mengetahui,<br>Kepala {{ $lab->name }}</p>
                <div class="ruang-ttd" style="height:36pt"></div>
                <span class="nama-ttd">{{ $lab->head?->name ?? '(...................................)' }}</span><br>
                <span class="kecil">{{ $lab->head?->nip ? 'NIP/NIDN. '.$lab->head->nip : '' }}</span>
            </td>
        </tr>
    </table>
</body>

</html>