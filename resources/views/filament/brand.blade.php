{{--
    Blok merek panel: kotak logo + nama aplikasi + nama institusi.
    Dipakai lewat ->brandLogo(fn () => view('filament.brand')).
    Warna teks diatur di filament/hooks/panel-style.blade.php
    (putih di bilah gelap, gelap di halaman login).
--}}
@php
    use App\Support\Sapra;

    $logo = Sapra::logoLight() ?? Sapra::favicon();
    $nama = Sapra::appName();
@endphp

<span class="sp-brand">
    <span class="sp-brand-mark">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @else
            {{ mb_substr($nama, 0, 1) }}
        @endif
    </span>

    <span class="sp-brand-text">
        <span class="sp-brand-name">{{ $nama }}</span>
        <span class="sp-brand-sub">{{ Sapra::institutionName() }}</span>
    </span>
</span>
