<x-filament-panels::page>
    @php
        $s = $this->stats();
        $pinjaman = $this->pinjamanBerjalan();
        $reservasi = $this->reservasiMendatang();
        $tugas = $this->tugasSaya();

        $baris = 'display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;'
            . 'gap:12px;padding:12px 0;border-bottom:1px solid rgba(128,128,128,.18)';
        $judul = 'margin:0;font-size:14px;font-weight:600';
        $sub = 'margin:2px 0 0;font-size:12px;opacity:.65';

        $kartu = [
            ['Menunggu', $s['menunggu'], 'Permohonan pinjam', '#F79009'],
            ['Siap Diambil', $s['siap'], 'Sudah disetujui', '#2E90FA'],
            ['Dipinjam', $s['dipinjam'], 'Sedang di tangan Anda', '#14315C'],
            ['Terlambat', $s['terlambat'], 'Segera kembalikan', '#D92D20'],
            ['Reservasi', $s['reservasi'], 'Ruang akan datang', '#12B76A'],
        ];

        if ($s['tugas'] !== null) {
            $kartu[] = ['Tugas Perbaikan', $s['tugas'], 'Ditugaskan ke Anda', '#7C3AED'];
        }
    @endphp

    {{-- Sapaan --}}
    <x-filament::section>
        <p style="margin:0;font-size:18px;font-weight:700">Halo, {{ auth()->user()->name }}</p>
        <p style="margin:4px 0 0;font-size:13px;opacity:.7">
            Ringkasan peminjaman dan reservasi milik Anda.
        </p>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px">
            @foreach ($this->pintasan() as [$label, $url, $ikon])
                <x-filament::button :href="$url" tag="a" :icon="$ikon" color="gray" size="sm">
                    {{ $label }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Kartu ringkas --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem">
        @foreach ($kartu as [$label, $nilai, $ket, $hex])
            <x-filament::section compact>
                <p style="margin:0;font-size:12px;font-weight:500;opacity:.65">{{ $label }}</p>
                <p style="margin:6px 0 0;font-size:30px;font-weight:700;line-height:1;
                          color: {{ $nilai > 0 ? $hex : 'rgba(128,128,128,.55)' }}">
                    {{ $nilai }}
                </p>
                <p style="margin:6px 0 0;font-size:11px;opacity:.6">{{ $ket }}</p>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Pinjaman berjalan --}}
    <x-filament::section icon="heroicon-o-arrows-right-left">
        <x-slot name="heading">Peminjaman Saya</x-slot>

        @forelse ($pinjaman as $l)
            <div style="{{ $baris }}">
                <div style="min-width:0">
                    <p style="{{ $judul }}">
                        {{ $l->loan_number }}
                        <x-filament::badge :color="$l->status->getColor()" style="margin-left:6px;display:inline-flex">
                            {{ $l->status->getLabel() }}
                        </x-filament::badge>
                    </p>
                    <p style="{{ $sub }}">
                        {{ $l->items->pluck('asset.name')->filter()->unique()->implode(', ') ?: 'Alat massal' }}
                        @if ($l->laboratory) · {{ $l->laboratory->name }} @endif
                    </p>
                    <p style="{{ $sub }}">
                        Kembali {{ $l->end_at?->locale('id')->translatedFormat('D, j M Y H.i') }}
                    </p>
                </div>
                <x-filament::button
                    :href="\App\Filament\Resources\Loans\LoanResource::getUrl('view', ['record' => $l])"
                    tag="a" size="sm" color="gray">
                    Lihat
                </x-filament::button>
            </div>
        @empty
            <p style="margin:0;font-size:13px;opacity:.6">Tidak ada peminjaman berjalan.</p>
        @endforelse
    </x-filament::section>

    {{-- Reservasi --}}
    <x-filament::section icon="heroicon-o-calendar-days">
        <x-slot name="heading">Reservasi Ruang Saya</x-slot>

        @forelse ($reservasi as $r)
            <div style="{{ $baris }}">
                <div style="min-width:0">
                    <p style="{{ $judul }}">{{ $r->title }}</p>
                    <p style="{{ $sub }}">{{ $r->room?->name }} · {{ $r->schedule_label }} · {{ $r->status_label }}</p>
                </div>
                <x-filament::button
                    :href="\App\Filament\Resources\RoomBookings\RoomBookingResource::getUrl('view', ['record' => $r])"
                    tag="a" size="sm" color="gray">
                    Lihat
                </x-filament::button>
            </div>
        @empty
            <p style="margin:0;font-size:13px;opacity:.6">Tidak ada reservasi yang akan datang.</p>
        @endforelse
    </x-filament::section>

    {{-- Tugas teknisi --}}
    @if ($this->punyaTugasTeknisi())
        <x-filament::section icon="heroicon-o-wrench-screwdriver">
            <x-slot name="heading">Tugas Perbaikan</x-slot>

            @forelse ($tugas as $w)
                <div style="{{ $baris }}">
                    <div style="min-width:0">
                        <p style="{{ $judul }}">{{ $w->number }} · {{ $w->asset?->code }}</p>
                        <p style="{{ $sub }}">
                            {{ $w->asset?->name }} · {{ $w->status->getLabel() }}
                            @if ($w->scheduled_at) · rencana {{ $w->scheduled_at->locale('id')->translatedFormat('j M Y') }} @endif
                        </p>
                    </div>
                    <x-filament::button
                        :href="\App\Filament\Resources\WorkOrders\WorkOrderResource::getUrl('view', ['record' => $w])"
                        tag="a" size="sm" color="gray">
                        Buka
                    </x-filament::button>
                </div>
            @empty
                <p style="margin:0;font-size:13px;opacity:.6">Tidak ada tugas terbuka.</p>
            @endforelse
        </x-filament::section>
    @endif
</x-filament-panels::page>