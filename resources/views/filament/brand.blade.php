{{--
    Blok merek panel: logo + nama aplikasi + nama institusi.
    Dipakai lewat ->brandLogo(fn () => view('filament.brand')).

    Dua versi logo:
    - sp-logo-terang : untuk latar terang (halaman login, lupa sandi)
    - sp-logo-gelap  : untuk latar gelap (pojok kiri topbar, laci menu)
      memakai "Logo mode gelap" di Pengaturan Sistem, bila ada.
    Tampil/sembunyi diatur di filament/hooks/panel-style.blade.php.
--}}
@php
    use App\Support\Sapra;

    $logoTerang = Sapra::logoLight() ?? Sapra::favicon();
    $logoGelap = Sapra::logoDark() ?? $logoTerang;
    $nama = Sapra::appName();
@endphp

<span class="sp-brand">
    @if ($logoTerang)
        <span class="sp-brand-mark sp-brand-mark-img">
            <img class="sp-logo-terang" src="{{ $logoTerang }}" alt="{{ $nama }}">
            <img class="sp-logo-gelap" src="{{ $logoGelap }}" alt="{{ $nama }}">
        </span>
    @else
        <span class="sp-brand-mark">{{ mb_substr($nama, 0, 1) }}</span>
    @endif

    <span class="sp-brand-text">
        <span class="sp-brand-name">{{ $nama }}</span>
        <span class="sp-brand-sub">{{ Sapra::institutionName() }}</span>
    </span>
</span>