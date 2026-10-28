{{--
    Panel grafis bersama untuk halaman login dan Cek Aset.

    Parameter opsional: $judul, $teks

    Perilaku:
    - Desktop (≥1024px): kolom kiri. Pembungkusnya harus grid dua kolom.
    - Tablet & ponsel: latar penuh di belakang konten, teks disembunyikan.
--}}
@php
    $t = \App\Support\Sapra::theme();
    $ringkas = \App\Services\StatsService::publicSummary();
    $lab = \App\Services\StatsService::labSummary();
    $akt = \App\Services\StatsService::publicActivity(6);

    $logo = \App\Support\Sapra::logoLight();
    $appName = \App\Support\Sapra::appName();
    $institusi = \App\Support\Sapra::institutionName();
@endphp

<aside class="sl-aside"
    style="--sl-primary: {{ $t['primary'] ?? '#14315C' }};
           --sl-hover: {{ $t['hover'] ?? '#0E2444' }};
           --sl-accent: {{ $t['accent'] ?? '#2E6BE6' }};
           --sl-teal: #34D3C4;">

    <div class="sl-kepala">
        <span class="sl-logo">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $institusi }}">
            @else
                <strong>{{ \Illuminate\Support\Str::of($appName)->substr(0, 2)->upper() }}</strong>
            @endif
        </span>
        <a href="/"><span class="sl-nama">{{ $appName }}</span></a>
    </div>

    <div class="sl-teks">
        <h1>{{ $judul ?? 'Satu pintu untuk seluruh aset kampus' }}</h1>
        <p>{{ $teks ?? '' }}</p>
    </div>

    @include('partials.aset-grafis', compact('ringkas', 'lab', 'akt'))

    <div class="sl-kaki">{{ $institusi }} · © {{ date('Y') }}</div>
</aside>

@once
<style>
    /* ---------- Panel grafis ---------- */
    .sl-aside {
        position: fixed;
        inset: 0;
        z-index: 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background:
            radial-gradient(circle at 88% 8%, color-mix(in srgb, var(--sl-accent) 45%, transparent), transparent 50%),
            radial-gradient(circle at 5% 100%, color-mix(in srgb, var(--sl-teal) 18%, transparent), transparent 45%),
            linear-gradient(155deg, var(--sl-primary), var(--sl-hover));
    }

    .sl-aside .sl-grafis {
        width: min(135vw, 720px);
        height: auto;
        opacity: .5;
        flex-shrink: 0;
    }

    .sl-aside .sl-kepala, .sl-aside .sl-teks, .sl-aside .sl-kaki { display: none; }

    /* Desktop: panel menjadi kolom kiri dari grid pembungkus */
    @media (min-width: 1024px) {
        .sl-aside {
            position: sticky;
            top: 0;
            grid-column: 1;
            grid-row: 1;
            height: 100vh;
            flex-direction: column;
            align-items: stretch;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 2.5rem 3.25rem;
        }

        .sl-aside .sl-teks, .sl-aside .sl-kaki { display: block; }

        .sl-aside .sl-kepala {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: -.01em;
        }

        .sl-logo {
            display: grid;
            place-items: center;
            height: 2.5rem;
            min-width: 2.5rem;
            padding: .35rem .6rem;
            border-radius: .75rem;
            background: #fff;
            color: var(--sl-primary);
        }

        .sl-logo img { height: 1.8rem; width: auto; }

        .sl-teks h1 {
            font-size: clamp(1.9rem, 2.6vw, 2.6rem);
            line-height: 1.12;
            font-weight: 800;
            letter-spacing: -.025em;
            max-width: 15ch;
        }

        .sl-teks p {
            margin-top: .9rem;
            max-width: 38ch;
            font-size: 1rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, .72);
        }

        .sl-aside .sl-grafis {
            width: 100%;
            max-width: 560px;
            max-height: 46vh;
            opacity: 1;
            align-self: center;
        }

        .sl-kaki {
            font-size: .8rem;
            color: rgba(255, 255, 255, .55);
        }
    }
</style>
@endonce