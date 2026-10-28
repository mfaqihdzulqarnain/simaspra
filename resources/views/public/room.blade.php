@php
    $t = $theme ?? \App\Support\Sapra::theme();
    $logo = \App\Support\Sapra::logoLight();
    $warnaStatus = [
        'dipakai' => ['bg' => '#FEF3F2', 'fg' => '#B42318', 'dot' => '#F04438'],
        'persiapan' => ['bg' => '#F2F4F7', 'fg' => '#344054', 'dot' => '#98A2B3'],
        'kosong' => ['bg' => '#ECFDF3', 'fg' => '#067647', 'dot' => '#12B76A'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $room->name }} — {{ $appName }}</title>
    @if ($favicon = \App\Support\Sapra::favicon())
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style type="text/tailwindcss">
        @theme {
            --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
            --color-navy: {{ $t['primary'] ?? '#14315C' }};
            --color-navy-600: {{ $t['hover'] ?? '#1E4079' }};
            --color-sky-brand: {{ $t['accent'] ?? '#2E6BE6' }};
            --color-ink: {{ $t['text'] ?? '#0F1E33' }};
            --color-muted: {{ $t['muted'] ?? '#64748B' }};
            --color-paper: {{ $t['bg'] ?? '#F6F8FC' }};
            --color-soft: {{ $t['soft'] ?? '#EEF3FB' }};
            --color-line: {{ $t['border'] ?? '#E4EAF2' }};
        }
    </style>
</head>

<body class="font-sans text-ink bg-paper antialiased min-h-screen">

    {{-- Kepala berwarna tema --}}
    <header class="bg-linear-to-br from-navy to-navy-600 text-white">
        <div class="max-w-xl mx-auto px-5 pt-6 pb-16">
            <div class="flex items-center gap-3 mb-6">
                @if ($logo)
                    <span class="bg-white rounded-lg px-2 py-1"><img src="{{ $logo }}" alt="" class="h-7 w-auto"></span>
                @endif
                <span class="text-sm font-semibold opacity-90">{{ $appName }}</span>
            </div>

            <p class="text-xs uppercase tracking-[1.5px] font-semibold opacity-70">Ruang</p>
            <h1 class="text-3xl font-extrabold tracking-tight mt-1">{{ $room->name }}</h1>
            <p class="text-sm opacity-80 mt-2">
                {{ collect([$room->building?->name, $room->floor ? 'Lantai '.$room->floor : null, $room->code])->filter()->implode(' · ') }}
            </p>
            @if ($room->capacity)
                <p class="text-sm opacity-80 mt-1">Kapasitas {{ $room->capacity }} orang</p>
            @endif
        </div>
    </header>

    <main class="max-w-xl mx-auto px-5 -mt-10 pb-12 space-y-4">

        @if ($jadwal)
            {{-- Keadaan saat ini --}}
            @php $w = $warnaStatus[$jadwal['sekarang']['kode']]; @endphp
            <section class="bg-white border border-line rounded-2xl p-5 shadow-[0_12px_32px_rgba(20,49,92,.10)]">
                <div class="flex items-center gap-2 text-xs font-semibold text-muted uppercase tracking-wide">
                    Saat ini · {{ now()->format('H.i') }}
                </div>
                <div class="mt-3 flex items-start gap-3">
                    <span class="mt-1.5 w-3 h-3 rounded-full shrink-0" style="background: {{ $w['dot'] }}"></span>
                    <div class="min-w-0">
                        <p class="text-xl font-bold" style="color: {{ $w['fg'] }}">{{ $jadwal['sekarang']['judul'] }}</p>
                        <p class="text-sm text-muted mt-0.5">{{ $jadwal['sekarang']['isi'] }}</p>
                    </div>
                </div>
            </section>

            {{-- Jadwal 7 hari --}}
            <section class="bg-white border border-line rounded-2xl p-5">
                <h2 class="font-bold text-navy">Jadwal 7 Hari</h2>

                <div class="mt-4 space-y-4">
                    @foreach ($jadwal['hari'] as $h)
                        <div>
                            <div class="flex items-baseline justify-between">
                                <p class="text-sm font-semibold {{ $loop->first ? 'text-sky-brand' : 'text-ink' }}">{{ $h['label'] }}</p>
                                <p class="text-xs text-muted">{{ $h['tanggal']->locale('id')->translatedFormat('j F Y') }}</p>
                            </div>

                            @if (empty($h['isi']))
                                <p class="text-sm text-muted mt-1.5">Kosong</p>
                            @else
                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($h['isi'] as $a)
                                        <li @class([
                                            'flex gap-3 rounded-lg px-3 py-2 text-sm border-l-4',
                                            'bg-emerald-50 border-emerald-500' => $a['jenis'] === 'acara' && ! $a['menunggu'],
                                            'bg-amber-50 border-amber-400 border-dashed' => $a['jenis'] === 'acara' && $a['menunggu'],
                                            'bg-slate-50 border-slate-300 border-dotted text-muted' => $a['jenis'] !== 'acara',
                                        ])>
                                            <span class="font-semibold tabular-nums shrink-0 w-24">{{ $a['jam'] }}</span>
                                            <span class="min-w-0">
                                                @if ($a['jenis'] === 'persiapan')
                                                    Persiapan · {{ $a['judul'] }}
                                                @elseif ($a['jenis'] === 'beres')
                                                    Beres-beres · {{ $a['judul'] }}
                                                @else
                                                    {{ $a['judul'] }}
                                                    @if ($a['menunggu'])
                                                        <span class="text-xs text-amber-700">(menunggu persetujuan)</span>
                                                    @endif
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($pesanUrl)
                <a href="{{ $pesanUrl }}"
                    class="block text-center rounded-xl px-5 py-3.5 font-semibold bg-navy text-white hover:bg-navy-600 transition">
                    Pesan Ruang Ini
                </a>
                <p class="text-xs text-center text-muted -mt-2">
                    Perlu masuk dengan akun. Pihak luar kampus dapat menghubungi Bagian Sarana dan Prasarana.
                </p>
            @endif
        @else
            <section class="bg-white border border-line rounded-2xl p-5 shadow-[0_12px_32px_rgba(20,49,92,.10)]">
                <p class="font-semibold text-navy">Ruang ini tidak dibuka untuk reservasi.</p>
                <p class="text-sm text-muted mt-1">Hubungi Bagian Sarana dan Prasarana untuk informasi pemakaian ruang.</p>
            </section>
        @endif

        <div class="flex flex-wrap gap-3 justify-center pt-2 text-sm">
            <a href="{{ route('asset.check') }}" class="text-muted hover:text-navy">Cek Aset</a>
            @if ($kirUrl)
                <span class="text-line">|</span>
                <a href="{{ $kirUrl }}" target="_blank" class="text-muted hover:text-navy">Cetak KIR</a>
            @endif
            <span class="text-line">|</span>
            <a href="{{ route('home') }}" class="text-muted hover:text-navy">Beranda</a>
        </div>
    </main>
</body>

</html>