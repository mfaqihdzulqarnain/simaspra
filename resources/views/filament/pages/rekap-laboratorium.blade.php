@push('styles')
<style>
    .sapra-select {
        padding: 8px 10px;
        font-size: 13px;
        border-radius: 8px;
        border: 1px solid rgba(128, 128, 128, .35);
        background: #fff;
        color: #101828;
        width: 100%;
    }

    .dark .sapra-select {
        background: #1f2937;
        color: #f9fafb;
        border-color: rgba(255, 255, 255, .18);
    }

    .sapra-select option {
        background: #fff;
        color: #101828;
    }

    .dark .sapra-select option {
        background: #1f2937;
        color: #f9fafb;
    }
</style>
@endpush
<x-filament-panels::page>

    @php
        $s = $this->summary();
        $top = $this->topAssets();
        $telat = $this->lateBorrowers();
        $nganggur = $this->idleAssets();
        $tren = $this->monthlyTrend();
        $keperluan = $this->purposeBreakdown();

        $maksTren = collect($tren)->max('jumlah') ?: 1;
        $maksTop = $top->max('jumlah') ?: 1;
        $totalKeperluan = $keperluan->sum('jumlah') ?: 1;

        $input = 'padding:8px 10px;font-size:13px;border-radius:8px;'
            . 'border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit';
        $baris = 'display:flex;align-items:center;justify-content:space-between;gap:12px;'
            . 'padding:10px 0;border-bottom:1px solid rgba(128,128,128,.15)';
        $label = 'display:block;margin:0 0 4px;font-size:12px;font-weight:500;opacity:.7';
    @endphp

    {{-- Penyaring --}}
    <x-filament::section compact>
        <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:end">
            <div style="min-width:220px">
                <label style="{{ $label }}">Laboratorium</label>
                <select wire:model.live="labId" class="sapra-select">
                    <option value="">Seluruh laboratorium</option>
                    @foreach ($this->labOptions() as $id => $nama)
                        <option value="{{ $id }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            <div style="min-width:180px">
                <label style="{{ $label }}">Periode</label>
                <select wire:model.live="periode" class="sapra-select">
                    @foreach ($this->periodeOptions() as $nilai => $teks)
                        <option value="{{ $nilai }}">{{ $teks }}</option>
                    @endforeach
                </select>
            </div>

            <p style="margin:0;font-size:12px;opacity:.6;padding-bottom:8px">
                {{ $this->dari()->translatedFormat('d M Y') }}
                &ndash; {{ $this->sampai()->translatedFormat('d M Y') }}
            </p>
        </div>
    </x-filament::section>

    {{-- Kartu ringkas --}}
    @php
        $kartu = [
            ['Total Peminjaman', $s['total_pinjaman'], 'Pada periode ini', '#14315C'],
            ['Sedang Berjalan', $s['berjalan'], 'Alat di luar lab', '#2E90FA'],
            ['Selesai', $s['selesai'], 'Sudah dikembalikan', '#12B76A'],
            ['Terlambat', $s['terlambat'], $s['persen_terlambat'] . '% dari yang selesai', '#D92D20'],
            ['Total Alat', $s['total_alat'], 'Tercatat aktif', '#14315C'],
            ['Dapat Dipinjam', $s['alat_dipinjamkan'], 'Dari total alat', '#2E90FA'],
        ];
    @endphp

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:1rem">
        @foreach ($kartu as [$judul, $nilai, $ket, $hex])
            <x-filament::section compact>
                <p style="margin:0;font-size:12px;font-weight:500;opacity:.65">{{ $judul }}</p>
                <p style="margin:6px 0 0;font-size:28px;font-weight:700;line-height:1;
                          color:{{ $nilai > 0 ? $hex : 'rgba(128,128,128,.55)' }}">
                    {{ $nilai }}
                </p>
                <p style="margin:6px 0 0;font-size:11px;opacity:.6">{{ $ket }}</p>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Tren bulanan --}}
    <x-filament::section icon="heroicon-o-chart-bar">
        <x-slot name="heading">Tren Peminjaman</x-slot>
        <x-slot name="description">
            {{ $this->dari()->translatedFormat('M Y') }}
            &ndash; {{ $this->sampai()->translatedFormat('M Y') }}
            · {{ $this->labName() }}
        </x-slot>

        <div style="display:flex;align-items:flex-end;gap:12px;height:130px;padding-top:10px">
            @foreach ($tren as $t)
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
                    <span style="font-size:12px;font-weight:600">{{ $t['jumlah'] }}</span>
                    <div style="width:100%;background:#14315C;border-radius:5px 5px 0 0;
                                height:{{ max(round($t['jumlah'] / $maksTren * 90), 3) }}px;
                                opacity:{{ $t['jumlah'] > 0 ? 1 : .25 }}"></div>
                    <span style="font-size:11px;opacity:.6">{{ $t['label'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Alat paling sering dipinjam --}}
    <x-filament::section icon="heroicon-o-fire" icon-color="warning">
        <x-slot name="heading">Alat Paling Sering Dipinjam</x-slot>

        @if ($top->isEmpty())
            <p style="margin:0;font-size:13px;opacity:.6">Belum ada data pada periode ini.</p>
        @else
            <div>
                @foreach ($top as $r)
                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:13px;font-weight:600">
                                <span style="font-family:monospace">{{ $r->asset->code }}</span>
                                <span style="font-weight:400"> · {{ $r->asset->name }}</span>
                            </p>
                            <div style="margin-top:5px;height:5px;border-radius:3px;
                                        background:rgba(128,128,128,.18);overflow:hidden">
                                <div style="height:5px;background:#F79009;
                                            width:{{ round($r->jumlah / $maksTop * 100) }}%"></div>
                            </div>
                        </div>
                        <x-filament::badge color="warning">{{ $r->jumlah }}×</x-filament::badge>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- Peminjam sering terlambat --}}
    <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
        <x-slot name="heading">Peminjam Sering Terlambat</x-slot>
        <x-slot name="description">
            Pertimbangkan pembinaan atau pembatasan bagi yang berulang kali terlambat.
        </x-slot>

        @if ($telat->isEmpty())
            <p style="margin:0;font-size:13px;opacity:.6">
                Tidak ada keterlambatan pada periode ini.
            </p>
        @else
            <div>
                @foreach ($telat as $r)
                    @php $wa = \App\Support\Phone::waLink($r->borrower_phone); @endphp

                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:13px;font-weight:600">{{ $r->borrower_name }}</p>
                            <p style="margin:2px 0 0;font-size:11px;opacity:.65">
                                {{ $r->borrower_identity ?? '—' }}
                                @if ($r->borrower_phone)
                                    · {{ \App\Support\Phone::display($r->borrower_phone) }}
                                @endif
                            </p>
                        </div>

                        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                            <x-filament::badge color="danger">{{ $r->jumlah }}× terlambat</x-filament::badge>

                            @if ($wa)
                                <x-filament::button
                                    tag="a" :href="$wa" target="_blank"
                                    color="gray" size="xs"
                                    icon="heroicon-m-chat-bubble-left-right"
                                >
                                    WA
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- Keperluan peminjaman --}}
    @if ($keperluan->isNotEmpty())
        <x-filament::section icon="heroicon-o-academic-cap" collapsible>
            <x-slot name="heading">Keperluan Peminjaman</x-slot>

            <div>
                @foreach ($keperluan as $k)
                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:13px">
                                {{ $this->purposeLabel($k->purpose_type?->value ?? $k->purpose_type) }}
                            </p>
                            <div style="margin-top:5px;height:5px;border-radius:3px;
                                        background:rgba(128,128,128,.18);overflow:hidden">
                                <div style="height:5px;background:#2E90FA;
                                            width:{{ round($k->jumlah / $totalKeperluan * 100) }}%"></div>
                            </div>
                        </div>
                        <span style="font-size:12px;font-weight:600;flex-shrink:0">
                            {{ $k->jumlah }}
                            <span style="font-weight:400;opacity:.6">
                                ({{ round($k->jumlah / $totalKeperluan * 100) }}%)
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Alat tidak pernah dipinjam --}}
    <x-filament::section icon="heroicon-o-archive-box" collapsible collapsed>
        <x-slot name="heading">Alat Belum Pernah Dipinjam</x-slot>
        <x-slot name="description">
            Pada periode ini. Periksa apakah alat masih dibutuhkan atau perlu dipindahkan.
        </x-slot>

        @if ($nganggur->isEmpty())
            <p style="margin:0;font-size:13px;opacity:.6">
                Seluruh alat yang dapat dipinjam pernah digunakan.
            </p>
        @else
            <div>
                @foreach ($nganggur as $a)
                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div>
                            <p style="margin:0;font-size:13px">
                                <span style="font-family:monospace;font-weight:600">{{ $a->code }}</span>
                                · {{ $a->name }}
                            </p>
                            <p style="margin:2px 0 0;font-size:11px;opacity:.6">
                                {{ $a->room?->name ?? '—' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>