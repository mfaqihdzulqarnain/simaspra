@php
    $kuitansi = $jenis === 'kuitansi';
    $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $rincian = $booking->fee_breakdown['hari'] ?? [];
    $penyesuaian = $booking->fee_breakdown['penyesuaian'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 8mm 15mm 12mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #000; line-height: 1.45; }

        .kop-img { width: 100%; margin: 0 0 10pt; }
        .kop-img img { width: 100%; height: auto; display: block; }
        .kop { width: 100%; border-bottom: 2.5pt solid #000; padding-bottom: 6pt; margin-bottom: 12pt; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop td.logo { width: 58pt; text-align: center; }
        .kop td.logo img { width: 50pt; height: auto; }
        .kop td.spacer { width: 58pt; }
        .kop .inst { font-size: 13pt; font-weight: bold; margin: 0; }
        .kop .meta { font-size: 7.5pt; margin: 2pt 0 0; }

        h1.judul { font-size: 13pt; text-align: center; margin: 0 0 2pt; text-decoration: underline; letter-spacing: 1pt; }
        p.sub { text-align: center; font-size: 9.5pt; margin: 0 0 14pt; }

        table.info { width: 100%; margin: 0 0 10pt; border-collapse: collapse; }
        table.info td { padding: 2pt 0; vertical-align: top; }
        table.info td.label { width: 120pt; }
        table.info td.sep { width: 10pt; }

        .jumlah { border: 1pt solid #000; padding: 7pt 10pt; margin: 6pt 0 4pt; }
        .jumlah .angka { font-size: 14pt; font-weight: bold; }
        .jumlah .kata { font-style: italic; margin-top: 2pt; text-transform: capitalize; }

        table.data { width: 100%; border-collapse: collapse; margin: 6pt 0 8pt; }
        table.data th, table.data td { border: 0.6pt solid #000; padding: 3pt 5pt; font-size: 8.5pt; }
        table.data th { background: #EFEFEF; text-align: center; }
        table.data td.c { text-align: center; }
        table.data td.r { text-align: right; }
        table.data tfoot td { font-weight: bold; }

        .catatan { font-size: 8pt; color: #333; margin-top: 6pt; }
        .lunas {
            display: inline-block; border: 2pt solid #067647; color: #067647; font-weight: bold;
            font-size: 12pt; padding: 3pt 12pt; letter-spacing: 2pt; transform: rotate(-6deg); margin-top: 6pt;
        }

        table.ttd { width: 100%; margin-top: 18pt; table-layout: fixed; border-collapse: collapse; page-break-inside: avoid; }
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

    <h1 class="judul">{{ $kuitansi ? 'KUITANSI' : 'TAGIHAN SEWA RUANG' }}</h1>
    <p class="sub">Nomor: {{ $kuitansi ? $booking->receipt_number : $booking->booking_number }}</p>

    <table class="info">
        <tr>
            <td class="label">{{ $kuitansi ? 'Telah terima dari' : 'Kepada' }}</td>
            <td class="sep">:</td>
            <td>
                <strong>{{ $booking->borrower_name }}</strong>
                @if ($booking->borrower_institution)
                    <br>{{ $booking->borrower_institution }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">{{ $kuitansi ? 'Untuk pembayaran' : 'Perihal' }}</td>
            <td class="sep">:</td>
            <td>
                Sewa {{ $booking->room?->name }}{{ $booking->room?->building ? ', '.$booking->room->building->name : '' }}
                untuk kegiatan "{{ $booking->title }}"
            </td>
        </tr>
        <tr>
            <td class="label">Jadwal</td>
            <td class="sep">:</td>
            <td>{{ $booking->schedule_label }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Reservasi</td>
            <td class="sep">:</td>
            <td>{{ $booking->booking_number }}</td>
        </tr>
        @if ($booking->rentalDecree)
            <tr>
                <td class="label">Dasar Tarif</td>
                <td class="sep">:</td>
                <td>SK {{ $booking->rentalDecree->number }} tentang {{ $booking->rentalDecree->title }}</td>
            </tr>
        @endif
    </table>

    @if (! empty($rincian))
        <table class="data">
            <thead>
                <tr>
                    <th style="width:20pt">No</th>
                    <th>Tanggal</th>
                    <th style="width:80pt">Jam Acara</th>
                    <th>Tarif</th>
                    <th style="width:90pt">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rincian as $i => $r)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td>{{ $r['tanggal'] }}</td>
                        <td class="c">{{ $r['jam'] }}</td>
                        <td>{{ $r['tarif'] }}</td>
                        <td class="r">{{ $rupiah($r['harga']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @if ($penyesuaian)
                    <tr>
                        <td colspan="4" class="r">Subtotal sesuai tarif</td>
                        <td class="r">{{ $rupiah($penyesuaian['dari']) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="r">Penyesuaian</td>
                        <td class="r">{{ $rupiah($penyesuaian['menjadi'] - $penyesuaian['dari']) }}</td>
                    </tr>
                @endif
                <tr>
                    <td colspan="4" class="r">Total</td>
                    <td class="r">{{ $rupiah($booking->fee_amount) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="jumlah">
        <div>{{ $kuitansi ? 'Uang sejumlah' : 'Jumlah yang harus dibayar' }}</div>
        <div class="angka">{{ $rupiah($booking->fee_amount) }}</div>
        <div class="kata">{{ $terbilang }}</div>
    </div>

    <p class="catatan">
        Biaya dihitung dari jam acara saja; hari persiapan dan beres-beres tidak dikenakan biaya.
        Perlengkapan pendukung (kursi, meja, sound system, proyektor) sudah termasuk dalam sewa ruang.
        @unless ($kuitansi)
            Pembayaran dilakukan sebelum serah terima ruang dan perlengkapan. Unggah bukti bayar melalui
            halaman reservasi atau serahkan ke Bagian Sarana dan Prasarana.
        @endunless
    </p>

    @if ($kuitansi)
        <p style="margin:4pt 0 0">
            Diterima pada {{ $booking->paid_at?->locale('id')->translatedFormat('d F Y') }}.
            <span class="lunas">LUNAS</span>
        </p>
    @endif

    <table class="ttd">
        <tr>
            <td>
                <p class="ttd-kota">&nbsp;</p>
                <p class="ttd-judul">{{ $kuitansi ? 'Penyetor,' : 'Pemesan,' }}</p>
                <p class="ttd-jabatan">{{ $booking->borrower_institution }}</p>
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $booking->borrower_name }}</span>
                <span class="nip-ttd"></span>
            </td>
            <td>
                <p class="ttd-kota">Pontianak, {{ ($kuitansi ? $booking->paid_at : now())?->locale('id')->translatedFormat('d F Y') }}</p>
                <p class="ttd-judul">{{ $kuitansi ? 'Penerima,' : 'Hormat kami,' }}</p>
                <p class="ttd-jabatan">Ka. Aset &amp; Sarana Prasarana</p>
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $signatory['name'] ?? '(...................................)' }}</span>
                <span class="nip-ttd">{{ ($signatory['nip'] ?? null) ? 'NIP/NIDN. '.$signatory['nip'] : '' }}</span>
            </td>
        </tr>
    </table>

</body>

</html>
