{{--
    Grafis ringkasan aset: grafik aktivitas, total aset, kondisi baik, QR, alat lab.
    Dipakai di halaman depan dan halaman login.

    Variabel opsional (diambil dari StatsService bila tidak dikirim):
    $ringkas, $lab, $akt, dan $kelas untuk kelas tambahan pada <svg>.

    Pembungkus wajib menyediakan warna lewat CSS variable:
    --sl-primary, --sl-hover, --sl-accent, --sl-teal
--}}
@php
    $ringkas = $ringkas ?? \App\Services\StatsService::publicSummary();
    $lab = $lab ?? \App\Services\StatsService::labSummary();
    $akt = $akt ?? \App\Services\StatsService::publicActivity(6);

    // --- Grafik aktivitas di dalam kartu (koordinat viewBox 520 x 400) ---
    $n = count($akt['labels']);
    $x0 = 66; $x1 = 350; $atas = 150; $dasar = 252;
    $slot = $n ? ($x1 - $x0) / $n : 0;
    $lebar = $slot * 0.42;
    $maks = max(1, max($akt['aset'] ?: [0]), max($akt['pinjam'] ?: [0]));
    $yAt = fn ($v) => $dasar - ($v / $maks * ($dasar - $atas));
    $xAt = fn ($i) => $x0 + $slot * $i + $slot / 2;
    $garis = collect($akt['pinjam'])
        ->map(fn ($v, $i) => round($xAt($i), 1) . ',' . round($yAt($v), 1))
        ->implode(' ');

    // --- Donat kondisi baik ---
    $persen = max(0, min(100, (float) ($ringkas['persen_baik'] ?? 0)));
    $keliling = 2 * M_PI * 28;

    // --- Pola QR dekoratif (deterministik, bukan kode sungguhan) ---
    $qr = 11;
    $sudut = fn ($r, $c, $b) => ($r < $b && $c < $b) || ($r < $b && $c >= $qr - $b) || ($r >= $qr - $b && $c < $b);
@endphp

<svg class="sl-grafis {{ $kelas ?? '' }}" viewBox="0 0 520 400" role="img"
    aria-label="Ringkasan aset: {{ $ringkas['total_aset'] ?? 0 }} aset, {{ $persen }}% kondisi baik">
    <defs>
        <pattern id="slTitik" width="18" height="18" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="1.2" fill="#fff" fill-opacity=".14" />
        </pattern>
        <linearGradient id="slBatang" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#fff" stop-opacity=".95" />
            <stop offset="1" stop-color="#fff" stop-opacity=".3" />
        </linearGradient>
        <filter id="slBayang" x="-20%" y="-20%" width="140%" height="160%">
            <feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#000" flood-opacity=".22" />
        </filter>
    </defs>

    <rect width="520" height="400" fill="url(#slTitik)" />

    {{-- Kartu grafik aktivitas --}}
    <g class="sl-muncul" style="animation-delay: 0ms">
        <rect x="40" y="76" width="332" height="212" rx="18" fill="#fff" fill-opacity=".08"
            stroke="#fff" stroke-opacity=".16" />
        <text class="sl-t-judul" x="62" y="106" font-size="13" font-weight="600" fill="#fff">Aktivitas {{ $n }} bulan</text>
        <rect x="62" y="117" width="9" height="9" rx="2" fill="#fff" fill-opacity=".85" />
        <text class="sl-t-kecil" x="76" y="125" font-size="10" fill="#fff" fill-opacity=".7">Aset baru</text>
        <rect x="146" y="120.5" width="12" height="2.5" rx="1" fill="var(--sl-teal)" />
        <text class="sl-t-kecil" x="162" y="125" font-size="10" fill="#fff" fill-opacity=".7">Peminjaman</text>

        <line x1="{{ $x0 - 6 }}" x2="{{ $x1 + 6 }}" y1="{{ $dasar }}" y2="{{ $dasar }}" stroke="#fff" stroke-opacity=".18" />

        @foreach ($akt['aset'] as $i => $v)
            @php $tinggi = $v > 0 ? max($dasar - $yAt($v), 3) : 0; @endphp
            @if ($v > 0)
                <rect class="sl-batang" x="{{ round($xAt($i) - $lebar / 2, 1) }}" y="{{ round($dasar - $tinggi, 1) }}"
                    width="{{ round($lebar, 1) }}" height="{{ round($tinggi, 1) }}" rx="3" fill="url(#slBatang)"
                    style="animation-delay: {{ 250 + $i * 70 }}ms">
                    <title>{{ $akt['labels'][$i] }}: {{ $v }} aset baru</title>
                </rect>
            @endif
            <text x="{{ round($xAt($i), 1) }}" y="{{ $dasar + 18 }}" class="sl-t-kecil" text-anchor="middle" font-size="10"
                fill="#fff" fill-opacity=".6">{{ $akt['labels'][$i] }}</text>
        @endforeach

        @if ($n)
            <polyline class="sl-garis" points="{{ $garis }}" pathLength="1" fill="none" stroke="var(--sl-teal)"
                stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
            @foreach ($akt['pinjam'] as $i => $v)
                <circle cx="{{ round($xAt($i), 1) }}" cy="{{ round($yAt($v), 1) }}" r="3.4"
                    fill="var(--sl-primary)" stroke="var(--sl-teal)" stroke-width="2">
                    <title>{{ $akt['labels'][$i] }}: {{ $v }} peminjaman</title>
                </circle>
            @endforeach
        @endif
    </g>

    {{-- Kartu total aset --}}
    <g class="sl-muncul" style="animation-delay: 120ms" filter="url(#slBayang)">
        <rect x="300" y="28" width="184" height="88" rx="16" fill="#fff" />
        <text class="sl-t-label" x="320" y="56" font-size="11" fill="#64748B">Total Aset</text>
        <text x="320" y="88" font-size="28" font-weight="800" fill="var(--sl-primary)">
            {{ number_format($ringkas['total_aset'] ?? 0, 0, ',', '.') }}</text>
        <text class="sl-t-kecil" x="320" y="104" font-size="10" fill="#0FA6A0">Di {{ $ringkas['total_unit'] ?? 0 }} unit pengelola</text>
        <rect x="436" y="44" width="30" height="30" rx="9" fill="var(--sl-accent)" fill-opacity=".12" />
        <path d="M444 54h14v10h-14z M447 51h8v3" fill="none" stroke="var(--sl-accent)" stroke-width="1.8"
            stroke-linejoin="round" />
    </g>

    {{-- Kartu kondisi baik --}}
    <g class="sl-muncul" style="animation-delay: 220ms" filter="url(#slBayang)">
        <rect x="388" y="168" width="112" height="132" rx="16" fill="#fff" />
        <g transform="rotate(-90 444 222)">
            <circle cx="444" cy="222" r="28" fill="none" stroke="#EEF3FB" stroke-width="9" />
            <circle class="sl-donat" cx="444" cy="222" r="28" fill="none" stroke="#0FA6A0" stroke-width="9"
                stroke-linecap="round"
                stroke-dasharray="{{ round($keliling * $persen / 100, 1) }} {{ round($keliling, 1) }}" />
        </g>
        <text x="444" y="227" text-anchor="middle" font-size="15" font-weight="800"
            fill="var(--sl-primary)">{{ round($persen) }}%</text>
        <text class="sl-t-kecil" x="444" y="280" text-anchor="middle" font-size="10.5" fill="#64748B">Kondisi baik</text>
    </g>

    {{-- Kartu QR --}}
    <g class="sl-muncul" style="animation-delay: 320ms" filter="url(#slBayang)">
        <rect x="18" y="292" width="176" height="96" rx="16" fill="#fff" />
        <g transform="translate(34 307)">
            @for ($r = 0; $r < $qr; $r++)
                @for ($c = 0; $c < $qr; $c++)
                    @php
                        // Tiga penanda sudut terisi penuh, sekelilingnya dikosongkan
                        $isi = $sudut($r, $c, 3)
                            || (! $sudut($r, $c, 4) && crc32("sapra{$r}-{$c}") % 5 < 2);
                    @endphp
                    @if ($isi)
                        <rect x="{{ $c * 6 }}" y="{{ $r * 6 }}" width="5.4" height="5.4" rx="1"
                            fill="var(--sl-primary)" />
                    @endif
                @endfor
            @endfor
        </g>
        <text class="sl-t-label" x="112" y="330" font-size="12" font-weight="700" fill="var(--sl-primary)">Pindai QR</text>
        <text class="sl-t-kecil" x="112" y="346" font-size="10" fill="#64748B">untuk cek</text>
        <text class="sl-t-kecil" x="112" y="360" font-size="10" fill="#64748B">detail aset</text>
    </g>

    {{-- Label alat laboratorium --}}
    <g class="sl-muncul" style="animation-delay: 420ms">
        <rect x="214" y="322" width="226" height="40" rx="20" fill="#fff" fill-opacity=".12" stroke="#fff"
            stroke-opacity=".22" />
        <circle cx="236" cy="342" r="4" fill="var(--sl-teal)" />
        <text class="sl-t-pill" x="250" y="346" font-size="12" fill="#fff">
            <tspan font-weight="700">{{ number_format($lab['alat'] ?? 0, 0, ',', '.') }} alat</tspan>
            <tspan fill-opacity=".75"> di {{ $lab['lab'] ?? 0 }} laboratorium</tspan>
        </text>
    </g>
</svg>

@once
    <style>
        .sl-grafis text { font-family: inherit; }

        /* Satu momen animasi saat halaman dibuka */
        @keyframes slMuncul { from { opacity: 0; transform: translateY(10px) } }
        @keyframes slNaik { from { transform: scaleY(0) } }
        @keyframes slGambar { from { stroke-dashoffset: 1 } }

        .sl-muncul { animation: slMuncul .6s ease both; }
        .sl-batang { transform-box: fill-box; transform-origin: bottom; animation: slNaik .7s ease both; }
        .sl-garis { stroke-dasharray: 1; animation: slGambar 1.1s .55s ease both; }

        /* Layar sempit: perbesar teks kecil agar tetap terbaca */
        @media (max-width: 640px) {
            .sl-perbesar .sl-t-kecil { font-size: 13px; }
            .sl-perbesar .sl-t-label { font-size: 14px; }
            .sl-perbesar .sl-t-judul { font-size: 15px; }
            .sl-perbesar .sl-t-pill { font-size: 13.5px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .sl-muncul, .sl-batang, .sl-garis { animation: none; }
        }
    </style>
@endonce