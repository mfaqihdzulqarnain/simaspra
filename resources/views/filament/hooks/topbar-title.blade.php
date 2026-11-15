{{--
    Judul di topbar, tepat di kanan sidebar: nama aplikasi + institusi.
    Disisipkan lewat render hook TOPBAR_START; posisinya diatur
    dengan CSS `order` di filament/hooks/panel-style.blade.php.
--}}
@php
    use App\Support\Sapra;
@endphp

<div class="sp-topbar-title">
    <span class="sp-topbar-name">{{ Sapra::appName() }}</span>
    <span class="sp-topbar-sub">{{ Sapra::institutionName() }} · {{ Sapra::heroTagline() }}</span>
</div>
