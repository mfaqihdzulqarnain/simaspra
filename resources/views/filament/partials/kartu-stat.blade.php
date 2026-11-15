{{--
    Kartu angka bergaya sama dengan StatsOverview (garis warna + ikon kanan atas).
    Pakai: @include('filament.partials.kartu-stat', [
        'label' => 'Terlambat', 'nilai' => 3, 'ket' => 'Perlu ditagih',
        'nada' => 'danger', 'ikon' => 'heroicon-o-exclamation-triangle',
    ])
    nada: primary | info | success | warning | danger | gray
    warning/danger bernilai 0 otomatis jadi gray (tidak ada masalah).
--}}
@php
    $nada ??= 'primary';
    $ikon ??= null;
    $ket ??= null;

    if (in_array($nada, ['warning', 'danger']) && (float) $nilai <= 0) {
        $nada = 'gray';
    }
@endphp

<div class="sp-kartu sp-tone-{{ $nada }}">
    <div class="sp-kartu-atas">
        <p class="sp-kartu-label">{{ $label }}</p>

        @if ($ikon)
            <span class="sp-kartu-ikon">
                <x-filament::icon :icon="$ikon" />
            </span>
        @endif
    </div>

    <p @class(['sp-kartu-nilai', 'sp-kartu-nol' => (float) $nilai <= 0])>{{ $nilai }}</p>

    @if ($ket)
        <p class="sp-kartu-ket">{{ $ket }}</p>
    @endif
</div>