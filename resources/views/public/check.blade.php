@php
    $t = $theme ?? \App\Support\Sapra::theme();
    $logo = \App\Support\Sapra::logoLight();
    $contohKode = \App\Support\Sapra::assetCodePrefix() . '-' . date('y') . '-LAP-0001';
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cek Aset — {{ \App\Support\Sapra::appName() }}</title>
    @if ($favicon = \App\Support\Sapra::favicon())
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Warna mengikuti tema di Pengaturan Sistem --}}
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

    <style>
        /* Tablet & ponsel: kartu di tengah, panel grafis jadi latar */
        .sl-utama {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }

        .sl-kartu { box-shadow: 0 24px 64px rgba(0, 0, 0, .35); }

        /* Desktop: panel kiri, kartu kanan */
        @media (min-width: 1024px) {
            .sl-layout {
                display: grid;
                grid-template-columns: minmax(0, 1.15fr) minmax(440px, 1fr);
                min-height: 100vh;
            }

            .sl-utama {
                grid-column: 2;
                grid-row: 1;
                padding: 3rem;
                background: var(--color-paper);
            }

            .sl-kartu { box-shadow: 0 1px 2px rgba(15, 30, 51, .04); }

            /* Logo sudah tampil di panel kiri */
            .sl-logo-kartu { display: none; }
        }
    </style>
</head>

<body class="font-sans text-ink bg-paper antialiased">

    <div class="sl-layout">

        @include('partials.panel-grafis', [
            'judul' => 'Kenali setiap aset lewat kodenya',
            'teks' => 'Lihat lokasi, kondisi, dan riwayat aset, atau laporkan kerusakan tanpa perlu masuk ke sistem.',
        ])

        <main class="sl-utama">
            <div class="sl-kartu w-full max-w-md bg-white border border-line rounded-2xl px-6 py-8 sm:px-10 sm:py-10">

                <div class="text-center mb-7">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="sl-logo-kartu h-14 w-auto mx-auto mb-5">
                    @endif
                    <h1 class="text-2xl font-extrabold tracking-tight text-navy">Cek Informasi Aset</h1>
                    <p class="text-sm text-muted mt-2">
                        Masukkan kode yang tertera pada stiker aset.
                    </p>
                </div>

                @if (session('error') || $errors->any())
                    <div class="mb-5 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800" role="alert">
                        {{ session('error') ?? $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('asset.check.submit') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="code" class="block text-sm font-semibold text-ink mb-1.5">Kode Aset atau Kode QR</label>
                        <div class="relative">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="7" height="7" rx="1.5" />
                                <rect x="14" y="3" width="7" height="7" rx="1.5" />
                                <rect x="3" y="14" width="7" height="7" rx="1.5" />
                                <path d="M14 14h3v3M21 14v.01M14 21h7M21 17v4" />
                            </svg>
                            <input id="code" type="text" name="code" required autofocus maxlength="40"
                                value="{{ old('code') }}" placeholder="{{ $contohKode }}"
                                autocomplete="off" autocapitalize="characters" spellcheck="false"
                                class="w-full rounded-xl border border-line bg-white pl-11 pr-4 py-3 text-sm font-mono uppercase tracking-wide
                                       placeholder:normal-case placeholder:tracking-normal placeholder:text-slate-400
                                       focus:outline-none focus:border-sky-brand focus:ring-4 focus:ring-sky-brand/15 transition">
                        </div>
                        <p class="text-xs text-muted mt-1.5">
                            Bisa kode aset lengkap atau kode pendek di bawah QR. Alat pemindai QR mengisinya otomatis.
                        </p>
                    </div>

                    <button type="submit"
                        class="w-full rounded-xl px-4 py-3 text-sm font-semibold text-white bg-navy hover:bg-navy-600 transition">
                        Cari Aset
                    </button>
                </form>

                <div class="mt-6 rounded-xl bg-soft border border-line px-4 py-3.5">
                    <p class="text-sm font-semibold text-navy">Punya ponsel berkamera?</p>
                    <p class="text-xs text-muted mt-1 leading-relaxed">
                        Arahkan kamera ke kode QR pada stiker. Halaman informasi aset terbuka otomatis tanpa perlu mengetik kode.
                    </p>
                </div>

                <div class="mt-6 pt-5 border-t border-line text-center">
                    <a href="{{ route('home') }}" class="text-sm font-medium text-muted hover:text-navy transition">
                        ← Kembali ke Beranda
                    </a>
                </div>
            </div>
        </main>
    </div>

</body>

</html>