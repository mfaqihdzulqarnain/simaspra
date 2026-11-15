{{--
    Gaya panel asetadmin: sidebar gelap, tampilan padat, kartu angka.
    Disisipkan di <head> lewat render hook HEAD_END.
    Warna mengikuti tema di Pengaturan Sistem (Sapra::theme()).

    CSS bawaan Filament ada di @layer, jadi aturan di sini otomatis
    menang tanpa !important. Setelah composer update versi besar,
    cek ulang kelas fi-* yang dipakai di bawah.
--}}
@php
    $t = \App\Support\Sapra::theme();
@endphp

<style>
    :root {
        --sp-side-bg: {{ $t['hover'] }};
        --sp-side-bg-2: {{ $t['primary'] }};
        --sp-accent: {{ $t['accent'] }};
        --sp-primary: {{ $t['primary'] }};
        --sp-soft: {{ $t['soft'] }};
        --sp-border: {{ $t['border'] }};
        --sp-text: {{ $t['text'] }};
        --sp-muted: {{ $t['muted'] }};
        --sp-bg: {{ $t['bg'] }};
        --sp-side-text: rgba(255, 255, 255, .80);
        --sp-side-muted: rgba(255, 255, 255, .48);
        --sp-side-hover: rgba(255, 255, 255, .07);
        --sp-side-active: rgba(255, 255, 255, .13);
    }

    .dark {
        --sp-side-bg: color-mix(in srgb, {{ $t['hover'] }} 70%, #000);
        --sp-side-bg-2: color-mix(in srgb, {{ $t['primary'] }} 70%, #000);
    }

    /* ------------------------------ Kepadatan ------------------------------ */

    /* Layar lebar: skala dasar 14px. Ponsel tetap 16px agar input tidak di-zoom iOS */
    @media (min-width: 1024px) {
        html.fi { font-size: 14px; }
    }

    .fi-body:not(.dark *) { background: var(--sp-bg); }

    .fi-page-header-main-ctn { padding-block: 1.25rem; row-gap: 1.25rem; }
    .fi-page-content { row-gap: 1.25rem; }
    .fi-page.fi-height-full .fi-page-main,
    .fi-page .fi-page-main { gap: 1.25rem; }
    .fi-header-heading { font-size: 1.5rem; line-height: 2rem; }
    .fi-header-subheading { margin-top: .25rem; font-size: 1rem; }

    .fi-section:not(.fi-section-not-contained):not(.fi-divided) > .fi-section-content-ctn > .fi-section-content { padding: 1rem 1.25rem; }
    .fi-section:not(.fi-section-not-contained) > .fi-section-header { padding: .75rem 1.25rem; }

    /* Tabel lebih rapat */
    .fi-ta-header-cell { padding-block: .625rem; }
    .fi-ta-text:not(.fi-inline),
    .fi-ta-icon:not(.fi-inline),
    .fi-ta-image:not(.fi-inline),
    .fi-ta-select:not(.fi-inline),
    .fi-ta-checkbox:not(.fi-inline),
    .fi-ta-color:not(.fi-inline) { padding-block: .625rem; }

    /* ------------------------------ Blok merek ----------------------------- */

    .sp-brand { display: inline-flex; align-items: center; gap: .625rem; height: 100%; min-width: 0; }
    .sp-brand-mark {
        display: grid; place-items: center; flex: none;
        height: 100%; aspect-ratio: 1; border-radius: .6rem;
        background: #fff; color: var(--sp-primary); font-weight: 800; font-size: 1.1rem;
        box-shadow: 0 0 0 1px var(--sp-border);
        overflow: hidden;
    }
    .sp-brand-mark img { width: 78%; height: 78%; object-fit: contain; }

    /* Logo gambar: tanpa kotak, latar transparan */
    .sp-brand-mark-img {
        background: transparent; box-shadow: none; border-radius: 0;
        aspect-ratio: auto; overflow: visible;
    }
    .sp-brand-mark-img img { display: block; width: auto; height: 2.5rem; max-width: 100%; object-fit: contain; }
    .fi-topbar-start .sp-brand-mark-img img,
    .fi-sidebar-header .sp-brand-mark-img img { height: 2.25rem; max-width: 10rem; }
    .fi-simple-header .sp-brand-mark-img img { height: 3rem; }

    /* Versi logo sesuai latar */
    .sp-logo-gelap { display: none !important; }
    .dark .sp-logo-terang,
    .fi-topbar-start .sp-logo-terang,
    .fi-sidebar-header .sp-logo-terang { display: none !important; }
    .dark .sp-logo-gelap,
    .fi-topbar-start .sp-logo-gelap,
    .fi-sidebar-header .sp-logo-gelap { display: block !important; }
    .sp-brand-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.15; }
    .sp-brand-name { font-weight: 800; font-size: 1rem; letter-spacing: .02em; color: var(--sp-text); }
    .sp-brand-sub {
        font-size: .7rem; color: var(--sp-muted);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 10rem;
    }
    .dark .sp-brand-name { color: #fff; }
    .dark .sp-brand-sub { color: rgba(255, 255, 255, .6); }

    /* Di bilah gelap (topbar kiri & header laci menu) teks selalu terang */
    .fi-topbar-start .sp-brand-name,
    .fi-sidebar-header .sp-brand-name { color: #fff; }
    .fi-topbar-start .sp-brand-sub,
    .fi-sidebar-header .sp-brand-sub { color: var(--sp-side-muted); }
    .fi-topbar-start .sp-brand-mark:not(.sp-brand-mark-img),
    .fi-sidebar-header .sp-brand-mark:not(.sp-brand-mark-img) { box-shadow: none; }

    /* Pojok gelap kiri topbar: hanya logo (nama & sub pindah ke judul topbar) */
    .fi-topbar-start .sp-brand-text { display: none; }

    /* Judul di topbar, tepat di kanan sidebar */
    .sp-topbar-title {
        order: 1; display: flex; flex-direction: column; justify-content: center;
        min-width: 0; line-height: 1.2; padding-inline: .5rem;
    }
    .sp-topbar-name { font-weight: 700; font-size: 1rem; color: var(--sp-primary); }
    .sp-topbar-sub {
        font-size: .75rem; color: var(--sp-muted);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .dark .sp-topbar-name { color: #fff; }
    .dark .sp-topbar-sub { color: rgba(255, 255, 255, .6); }
    .fi-topbar-end { order: 2; }
    @media (max-width: 639px) { .sp-topbar-sub { display: none; } }

    /* Halaman login / lupa sandi (ponsel): merek sedikit lebih besar */
    .fi-simple-header .fi-logo { height: 3rem !important; }

    /* ---------------------------- Sidebar gelap ---------------------------- */

    .fi-sidebar.fi-main-sidebar {
        background: linear-gradient(180deg, var(--sp-side-bg) 0%, var(--sp-side-bg-2) 100%);
    }

    /* Header laci menu (ponsel/tablet) */
    .fi-body-has-topbar .fi-sidebar-header {
        background: var(--sp-side-bg);
        box-shadow: none;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
    }
    .fi-sidebar-header .fi-icon-btn,
    .fi-sidebar-header .fi-icon { color: var(--sp-side-text); }

    .fi-sidebar-nav { padding: 1rem .875rem 1.5rem; row-gap: 1.25rem; }
    .fi-sidebar-nav-groups { margin-inline: 0; row-gap: 1rem; }
    .fi-sidebar-group,
    .fi-sidebar-group-items { row-gap: 2px; }

    /* Judul kelompok: kecil, kapital, garis tipis di kanan */
    .fi-sidebar-group-btn { padding: .25rem .5rem; gap: .5rem; }
    .fi-sidebar-group-label {
        display: flex; align-items: center; gap: .5rem;
        font-size: .68rem; line-height: 1rem; font-weight: 700;
        letter-spacing: .08em; text-transform: uppercase;
        color: var(--sp-side-muted);
    }
    .fi-sidebar-group-label::after {
        content: ""; flex: 1; height: 1px; background: rgba(255, 255, 255, .10);
    }
    .fi-sidebar-group-btn .fi-icon,
    .fi-sidebar-group-collapse-btn,
    .fi-sidebar-group-collapse-btn .fi-icon { color: var(--sp-side-muted); }

    /* Item menu */
    .fi-sidebar-item-btn { padding: .45rem .625rem; gap: .625rem; border-radius: .5rem; }
    .fi-sidebar-item-btn > .fi-icon { color: var(--sp-side-muted); }
    .fi-sidebar-item-label { color: var(--sp-side-text); font-size: .9rem; }

    .fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover,
    .fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:focus-visible {
        background: var(--sp-side-hover);
    }
    .fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover > .fi-icon { color: #fff; }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: var(--sp-side-active);
        box-shadow: inset 3px 0 0 var(--sp-accent);
    }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon { color: var(--sp-accent); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label { color: #fff; font-weight: 600; }

    /* Sub-menu bertitik */
    .fi-sidebar-item-grouped-border-part-not-first,
    .fi-sidebar-item-grouped-border-part-not-last { background: rgba(255, 255, 255, .15); }
    .fi-sidebar-item-grouped-border-part { background: var(--sp-side-muted); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-grouped-border-part { background: var(--sp-accent); }

    /* Ikon grup saat sidebar diciutkan */
    .fi-sidebar-group-dropdown-trigger-btn .fi-icon { color: var(--sp-side-muted); }
    .fi-sidebar-group.fi-active .fi-sidebar-group-dropdown-trigger-btn .fi-icon { color: var(--sp-accent); }
    .fi-sidebar-group-dropdown-trigger-btn:hover { background: var(--sp-side-hover); }

    /* Scrollbar tipis di sidebar */
    .fi-sidebar-nav { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, .18) transparent; }

    /* ------------------------------- Topbar -------------------------------- */

    /* Desktop: pojok kiri topbar ikut gelap selebar sidebar, menyatu dengan sidebar */
    @media (min-width: 1024px) {
        .fi-topbar { padding-inline-start: 0; }

        .fi-topbar-start {
            align-self: stretch;
            width: var(--sidebar-width);
            margin-inline-end: 1rem;
            padding-inline: 0;
            gap: .25rem;
            background: var(--sp-side-bg);
            transition: width .2s ease;
        }
        .fi-topbar-start { position: relative; }

        /* Tombol ciutkan menempel kiri, logo di tengah blok gelap */
        .fi-topbar-start .fi-topbar-collapse-sidebar-btn-ctn {
            position: absolute; inset-inline-start: .75rem; top: 50%; transform: translateY(-50%);
            z-index: 1;
        }
        .fi-topbar-start > a {
            flex: 1; display: flex; align-items: center; justify-content: center;
            height: 100%; min-width: 0; padding-inline: 2.75rem;
        }
        .fi-topbar-start .fi-logo { margin: 0; height: auto !important; display: flex; align-items: center; justify-content: center; }
        .fi-topbar-start .fi-icon-btn,
        .fi-topbar-start .fi-icon { color: var(--sp-side-text); }
        .fi-topbar-start .fi-icon-btn:hover { background: var(--sp-side-hover); }

        /* Sidebar diciutkan: hanya tombol buka */
        .fi-body:has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar-start {
            width: var(--collapsed-sidebar-width);
            justify-content: center;
            padding-inline: 0;
        }
        .fi-body:has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar-start .fi-topbar-collapse-sidebar-btn-ctn {
            position: static; transform: none;
        }
        .fi-body:has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar-start .fi-logo,
        .fi-body:has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar-start > a { display: none; }
    }

    /* ---------------------------- Kartu angka ------------------------------ */

    .fi-wi-stats-overview-stat { padding: 1.125rem 1.125rem 1rem; border-radius: .875rem; }

    /* Garis warna di atas setiap kartu */
    .sp-stat { overflow: hidden; }
    .sp-stat::before {
        content: ""; position: absolute; inset-inline: 0; top: 0; height: 4px;
        background: var(--sp-tone-line);
    }
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-content { row-gap: .35rem; }
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-label-ctn { padding-inline-end: 2.75rem; min-height: 2.25rem; }
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-label { font-size: .8rem; line-height: 1.1rem; }
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-value { font-size: 1.65rem; line-height: 2rem; }
    .fi-wi-stats-overview-stat .fi-wi-stats-overview-stat-description { font-size: .78rem; }

    /* Ikon kartu di pojok kanan atas, dalam kotak berwarna */
    .sp-stat .fi-wi-stats-overview-stat-label-ctn > .fi-icon {
        position: absolute; top: 1rem; inset-inline-end: 1.125rem;
        width: 2.25rem; height: 2.25rem; padding: .5rem;
        border-radius: .6rem;
        background: var(--sp-tone-bg); color: var(--sp-tone-fg);
    }

    .sp-tone-primary { --sp-tone-bg: var(--sp-soft); --sp-tone-fg: var(--sp-primary); --sp-tone-line: var(--sp-primary); }
    .sp-tone-success { --sp-tone-bg: #DCFAE6; --sp-tone-fg: #067647; --sp-tone-line: #12B76A; }
    .sp-tone-warning { --sp-tone-bg: #FEF0C7; --sp-tone-fg: #B54708; --sp-tone-line: #F79009; }
    .sp-tone-danger  { --sp-tone-bg: #FEE4E2; --sp-tone-fg: #B42318; --sp-tone-line: #F04438; }
    .sp-tone-gray    { --sp-tone-bg: #F2F4F7; --sp-tone-fg: #667085; --sp-tone-line: var(--sp-accent); }

    .dark .sp-tone-primary { --sp-tone-bg: rgba(255, 255, 255, .08); --sp-tone-fg: var(--sp-accent); --sp-tone-line: var(--sp-accent); }
    .dark .sp-tone-success { --sp-tone-bg: rgba(18, 183, 106, .15); --sp-tone-fg: #47CD89; }
    .dark .sp-tone-warning { --sp-tone-bg: rgba(247, 144, 9, .15); --sp-tone-fg: #FDB022; }
    .dark .sp-tone-danger  { --sp-tone-bg: rgba(240, 68, 56, .15); --sp-tone-fg: #F97066; }
    .dark .sp-tone-gray    { --sp-tone-bg: rgba(255, 255, 255, .06); --sp-tone-fg: #98A2B3; }
</style>