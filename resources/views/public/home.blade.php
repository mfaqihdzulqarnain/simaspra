@php
    $t = $theme ?? \App\Support\Sapra::theme();
    $cekUrl = route('asset.check');
    $masukUrl = url('/asetadmin');
    $logo = \App\Support\Sapra::logoLight();
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName ?? 'SIMASPRA' }} — Manajemen Aset &amp; Sarana Prasarana</title>
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
            --color-teal-brand: #0FA6A0;
            --color-ink: {{ $t['text'] ?? '#0F1E33' }};
            --color-paper: {{ $t['bg'] ?? '#F6F8FC' }};
            --color-soft: {{ $t['soft'] ?? '#EEF3FB' }};
            --color-line: {{ $t['border'] ?? '#E4EAF2' }};
        }
    </style>

    <style>
        @keyframes pulseDot { 0%, 100% { opacity: 1 } 50% { opacity: .35 } }
        .pulse { animation: pulseDot 1.6s infinite }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto }
            .pulse { animation: none }
            #backToTop { transition: none }
        }
    </style>
</head>

<body class="font-sans text-ink bg-paper antialiased">

    {{-- NAVBAR --}}
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-sm border-b border-line">
        <nav class="max-w-6xl mx-auto px-6 h-17.5 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3 font-extrabold text-navy min-w-0">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $institution ?? 'Logo' }}" class="h-20 w-auto">
                @else
                    <span class="w-9 h-9 rounded-lg grid place-items-center text-white text-sm bg-linear-to-br from-navy to-sky-brand">SA</span>
                @endif
                {{-- <span class="truncate">{{ $appName ?? 'SIMASPRA' }}</span> --}}
            </a>
            <div class="hidden md:flex items-center gap-7 text-sm text-slate-500">
                <a href="#statistik" class="hover:text-navy">Statistik</a>
                <a href="#alur-ajuan" class="hover:text-navy">Pengajuan</a>
                <a href="#alur-peminjaman" class="hover:text-navy">Peminjaman</a>
                <a href="#alur-pemeliharaan" class="hover:text-navy">Pemeliharaan</a>
                <a href="#alur-pelaporan" class="hover:text-navy">Pelaporan</a>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ $cekUrl }}"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold bg-navy text-white hover:bg-navy-600 transition">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                    Cek Aset
                </a>
            </div>
        </nav>
    </header>

    {{-- HERO + STATISTIK --}}
    <section class="relative overflow-hidden">
        <div class="absolute -top-24 -right-28 w-130 h-130 rounded-full bg-[radial-gradient(circle,rgba(46,107,230,.14),transparent_65%)]"></div>
        <div class="max-w-6xl mx-auto px-6 py-20 grid lg:grid-cols-2 gap-14 items-center relative">
            <div>
                <span class="inline-flex items-center gap-2 text-[13px] font-semibold text-navy bg-soft border border-line px-4 py-1.5 rounded-full mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-teal-brand"></span> Manajemen Aset &amp; Sarana Prasarana
                </span>
                <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight leading-[1.1] text-navy">
                    Aset kampus terkelola,<br>
                    <span class="bg-linear-to-r from-sky-brand to-teal-brand bg-clip-text text-transparent">terpantau secara nyata</span>
                </h1>
                <p class="text-lg text-slate-500 mt-5 max-w-md">
                    Satu sistem untuk seluruh siklus aset {{ $institution ?? 'Politeknik Aisyiyah Pontianak' }}:
                    pengadaan, peminjaman aset dan alat laboratorium, pemeliharaan, hingga pelaporan.
                </p>
                <div class="flex flex-wrap gap-3.5 mt-8">
                    <a href="{{ $cekUrl }}"
                        class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold bg-navy text-white hover:bg-navy-600 transition">
                        Cek Aset Sekarang →
                    </a>
                    <a href="#alur-ajuan"
                        class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold bg-white text-navy border border-line hover:bg-soft">
                        Lihat Alur Sistem
                    </a>
                </div>
            </div>

            {{-- Grafis ringkasan aset (sama dengan halaman login) --}}
            <div id="statistik"
                class="scroll-mt-24 relative overflow-hidden rounded-3xl p-3 sm:p-5 text-white shadow-[0_24px_60px_rgba(20,49,92,.28)]"
                style="--sl-primary: var(--color-navy);
                       --sl-hover: var(--color-navy-600);
                       --sl-accent: var(--color-sky-brand);
                       --sl-teal: #34D3C4;
                       background:
                           radial-gradient(circle at 88% 8%, color-mix(in srgb, var(--sl-accent) 45%, transparent), transparent 50%),
                           radial-gradient(circle at 5% 100%, color-mix(in srgb, var(--sl-teal) 18%, transparent), transparent 45%),
                           linear-gradient(155deg, var(--sl-primary), var(--sl-hover));">
                <div class="flex items-center justify-between gap-3 px-2 pt-1">
                    <div class="font-bold text-sm">Ikhtisar Aset, Semua Unit</div>
                    <div class="text-xs flex items-center gap-2 font-semibold text-[#34D3C4]">
                        <span class="w-2 h-2 rounded-full bg-[#34D3C4] pulse"></span> Data Terkini
                    </div>
                </div>

                @include('partials.aset-grafis', [
                    'ringkas' => $ringkas ?? null,
                    'lab' => $lab ?? null,
                    'akt' => $aktivitas ?? null,
                    'kelas' => 'sl-perbesar block w-full h-auto',
                ])
            </div>
        </div>
    </section>

    {{-- ALUR PENGAJUAN --}}
    <section id="alur-ajuan" class="scroll-mt-16 py-20">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-center text-sky-brand font-bold text-[13px] tracking-[1.5px] uppercase">Alur Pengajuan</p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-center text-navy mt-3 mb-3 tracking-tight">
                Dari kebutuhan unit hingga serah terima
            </h2>
            <p class="text-center text-slate-500 max-w-lg mx-auto mb-12">
                Persetujuan bertingkat memastikan setiap pengadaan tercatat dan terverifikasi.
            </p>
            @php
                $ajuan = [
                    ['Pengajuan', 'Kaprodi atau Kabag mengajukan kebutuhan beserta spesifikasi dan perkiraan biaya.'],
                    ['Verifikasi Sarpras', 'Ka. Sarpras memeriksa kelayakan, lalu meneruskan, meminta revisi, atau menolak.'],
                    ['Persetujuan Wadir', 'Wakil Direktur memberi keputusan akhir.'],
                    ['Pencatatan Aset', 'Barang yang datang dicatat sebagai aset dan diberi stiker QR.'],
                    ['Serah Terima', 'Aset disalurkan ke unit dengan Berita Acara Serah Terima.'],
                ];
            @endphp
            <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-5">
                @foreach ($ajuan as $i => [$judul, $isi])
                    <div class="bg-white border border-line rounded-2xl p-6 hover:shadow-[0_12px_32px_rgba(20,49,92,.08)] transition">
                        <div class="w-10 h-10 rounded-full grid place-items-center font-extrabold text-white bg-linear-to-br from-sky-brand to-teal-brand mb-4">
                            {{ $i + 1 }}</div>
                        <h3 class="font-bold text-navy mb-1.5">{{ $judul }}</h3>
                        <p class="text-sm text-slate-500">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>
            <p class="text-center text-sm text-slate-500 mt-8">
                Status pengajuan bisa dipantau pengaju kapan saja. Pengajuan yang diminta revisi kembali ke pengaju untuk diperbaiki.
            </p>
        </div>
    </section>

    {{-- ALUR PEMINJAMAN --}}
    <section id="alur-peminjaman" class="scroll-mt-16 py-20 bg-white border-y border-line">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-center text-sky-brand font-bold text-[13px] tracking-[1.5px] uppercase">Alur Peminjaman</p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-center text-navy mt-3 mb-3 tracking-tight">
                Pinjam sesuai jadwal, tanpa bentrok
            </h2>
            <p class="text-center text-slate-500 max-w-lg mx-auto mb-12">
                Sistem hanya menampilkan barang yang benar-benar bebas pada jam yang Anda pilih.
            </p>

            @php
                $jalur = [
                    [
                        'judul' => 'Aset Umum',
                        'untuk' => 'Sivitas kampus dan pihak luar',
                        'warna' => 'from-navy to-sky-brand',
                        'langkah' => [
                            ['Ajukan peminjaman', 'Isi keperluan serta tanggal dan jam pinjam, lalu pilih aset yang tersedia.'],
                            ['Persetujuan Sarpras', 'Ka. Sarpras memeriksa dan menyetujui permohonan.'],
                            ['Serah terima', 'Kondisi aset dicatat dan Berita Acara Peminjaman terbit.'],
                            ['Pengembalian', 'Kondisi diperiksa ulang. Kerusakan otomatis tercatat sebagai laporan.'],
                        ],
                        'catatan' => 'Peminjam dari luar kampus memerlukan akun dari Bagian Sarana dan Prasarana.',
                    ],
                    [
                        'judul' => 'Alat Laboratorium',
                        'untuk' => 'Dosen dan mahasiswa',
                        'warna' => 'from-sky-brand to-teal-brand',
                        'langkah' => [
                            ['Masuk ke sistem', 'Mahasiswa melakukan ajuan peminjaman alat laboratorium bersamaan dengan kesaksian Laboran.'],
                            ['Pilih alat', 'Hanya alat yang bebas pada jam itu yang tampil. Alat tertentu khusus untuk dosen.'],
                            ['Persetujuan laboran', 'Laboran lab bersangkutan menyetujui dan Anda menerima notifikasi.'],
                            ['Ambil di lab', 'Laboran memindai QR alat saat menyerahkannya kepada Anda.'],
                            ['Kembalikan tepat waktu', 'Laboran memindai QR dan memeriksa kondisi alat. Yang terlambat diingatkan lewat WhatsApp.'],
                        ],
                        'catatan' => 'Batas lama pinjam diatur per lab, dan batas mahasiswa lebih pendek daripada dosen.',
                    ],
                ];
            @endphp

            <div class="grid lg:grid-cols-2 gap-6">
                @foreach ($jalur as $j)
                    <div class="bg-paper border border-line rounded-2xl p-7 flex flex-col">
                        <div class="mb-6">
                            <h3 class="text-xl font-extrabold text-navy">{{ $j['judul'] }}</h3>
                            <p class="text-sm text-slate-500 mt-1">{{ $j['untuk'] }}</p>
                        </div>

                        <ol class="relative flex-1">
                            @foreach ($j['langkah'] as $i => [$judul, $isi])
                                <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                                    @unless ($loop->last)
                                        <span class="absolute left-4.25 top-9 bottom-0 w-px bg-line" aria-hidden="true"></span>
                                    @endunless
                                    <span class="relative shrink-0 w-9 h-9 rounded-full grid place-items-center text-sm font-extrabold text-white bg-linear-to-br {{ $j['warna'] }}">
                                        {{ $i + 1 }}
                                    </span>
                                    <div class="pt-1.5 min-w-0">
                                        <p class="font-bold text-navy text-[15px]">{{ $judul }}</p>
                                        <p class="text-sm text-slate-500 mt-0.5">{{ $isi }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>

                        <p class="mt-6 text-sm text-slate-600 bg-white border border-line rounded-xl px-4 py-3">
                            {{ $j['catatan'] }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm text-slate-500">
                <span>Sudah punya akun?</span>
                <a href="{{ $masukUrl }}"
                    class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 font-semibold bg-navy text-white hover:bg-navy-600 transition">
                    Masuk untuk Meminjam
                </a>
            </div>
        </div>
    </section>

    {{-- ALUR PEMELIHARAAN (band navy) --}}
    <section id="alur-pemeliharaan" class="scroll-mt-16 py-20 bg-navy text-white">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-center text-sky-300 font-bold text-[13px] tracking-[1.5px] uppercase">Alur Pemeliharaan</p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-center mt-3 mb-3 tracking-tight">Jaga aset tetap prima</h2>
            <p class="text-center text-slate-300 max-w-lg mx-auto mb-12">
                Siapa pun bisa melapor kerusakan cukup dengan memindai QR, tanpa perlu masuk ke sistem.
            </p>
            @php
                $rawat = [
                    ['Laporan Kerusakan', 'Pindai QR pada stiker aset, lalu isi tingkat kerusakan dan keterangannya.'],
                    ['Perintah Kerja', 'Sarpras, atau laboran untuk alat lab, menugaskan teknisi.'],
                    ['Perbaikan', 'Teknisi memperbaiki, lalu mencatat biaya dan kondisi akhir aset.'],
                    ['Aset Kembali Aktif', 'Status aset diperbarui dan riwayat perbaikannya tersimpan.'],
                ];
            @endphp
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($rawat as $i => [$judul, $isi])
                    <div class="bg-white/5 border border-white/12 rounded-2xl p-6">
                        <div class="w-10 h-10 rounded-full grid place-items-center font-extrabold text-white bg-linear-to-br from-sky-brand to-teal-brand mb-4">
                            {{ $i + 1 }}</div>
                        <h3 class="font-bold mb-1.5">{{ $judul }}</h3>
                        <p class="text-sm text-slate-300">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>
            <p class="text-center text-sm text-slate-300 mt-8 max-w-2xl mx-auto">
                Pemeliharaan preventif dan kalibrasi berjalan terjadwal. Perintah kerjanya dibuat otomatis
                sebelum jatuh tempo, sehingga kerusakan bisa dicegah sejak awal.
            </p>
        </div>
    </section>

    {{-- ALUR PELAPORAN --}}
    <section id="alur-pelaporan" class="scroll-mt-16 py-20">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-center text-sky-brand font-bold text-[13px] tracking-[1.5px] uppercase">Alur Pelaporan</p>
            <h2 class="text-3xl md:text-4xl font-extrabold text-center text-navy mt-3 mb-3 tracking-tight">
                Audit &amp; laporan terdokumentasi
            </h2>
            <p class="text-center text-slate-500 max-w-lg mx-auto mb-12">
                Stock opname dan penghapusan tercatat rapi dengan jejak audit lengkap.
            </p>
            @php
                $lapor = [
                    ['Stock Opname', 'Petugas memindai QR setiap aset per ruang dan mencatat kondisinya.'],
                    ['Rekonsiliasi', 'Temuan dikelompokkan: sesuai, beda lokasi, beda kondisi, atau tidak ditemukan. Berita acara terbit saat periode ditutup.'],
                    ['Usulan Penghapusan', 'Aset tak layak diperiksa Ka. Sarpras, lalu diputuskan Wakil Direktur.'],
                    ['Arsip & Laporan', 'Berita Acara Penghapusan terbit. Riwayat aset tetap tersimpan untuk audit dan pimpinan.'],
                ];
            @endphp
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ($lapor as $i => [$judul, $isi])
                    <div class="bg-white border border-line rounded-2xl p-6 hover:shadow-[0_12px_32px_rgba(20,49,92,.08)] transition">
                        <div class="w-10 h-10 rounded-full grid place-items-center font-extrabold text-white bg-linear-to-br from-navy to-sky-brand mb-4">
                            {{ $i + 1 }}</div>
                        <h3 class="font-bold text-navy mb-1.5">{{ $judul }}</h3>
                        <p class="text-sm text-slate-500">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="pb-24">
        <div class="max-w-7xl mx-auto px-6">
            <div class="bg-linear-to-br from-navy to-navy-600 text-white text-center rounded-3xl p-10 md:p-14">
                <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-3">
                    Butuh pemeriksaan atau melaporkan aset?
                </h2>
                <p class="text-slate-300 max-w-md mx-auto mb-7">
                    Pindai QR atau masukkan kode aset untuk melihat detail, lokasi, dan kondisinya, sekaligus melaporkan kerusakan.
                </p>
                <div class="flex flex-wrap gap-3.5 justify-center">
                    <a href="{{ $cekUrl }}"
                        class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold bg-white text-navy hover:bg-soft transition">
                        Cek Aset Sekarang →
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="border-t border-line py-8 text-center text-sm text-slate-500 bg-white">
        {{ $appName ?? 'SIMASPRA' }} — Sistem Manajemen Aset &amp; Sarana Prasarana · PUSDATIN
        {{ $institution ?? 'Politeknik Aisyiyah Pontianak' }} · © {{ date('Y') }}
    </footer>

    <button id="backToTop" type="button" aria-label="Kembali ke atas"
        class="fixed bottom-6 right-6 z-50 w-12 h-12 rounded-full bg-navy text-white
               shadow-[0_8px_24px_rgba(20,49,92,.28)] grid place-items-center
               opacity-0 translate-y-3 pointer-events-none
               transition-all duration-300 hover:bg-navy-600">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m18 15-6-6-6 6" />
        </svg>
    </button>

    <script>
        (function () {
            const btn = document.getElementById('backToTop');
            if (!btn) return;

            const tampil = () => {
                const lewat = window.scrollY > 320;
                btn.classList.toggle('opacity-0', !lewat);
                btn.classList.toggle('translate-y-3', !lewat);
                btn.classList.toggle('pointer-events-none', !lewat);
            };

            window.addEventListener('scroll', tampil, { passive: true });
            tampil();

            btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        })();
    </script>
</body>

</html>