@push('styles')
<style>
    .sapra-input {
        padding: 8px 10px; font-size: 13px; border-radius: 8px;
        border: 1px solid rgba(128,128,128,.35);
        background: #fff; color: #101828;
    }
    .dark .sapra-input { background: #1f2937; color: #f9fafb; border-color: rgba(255,255,255,.18); }
    .sapra-input option { background: #fff; color: #101828; }
    .dark .sapra-input option { background: #1f2937; color: #f9fafb; }
</style>
@endpush

<x-filament-panels::page>

    @php
        $r = $this->ringkas();
        $a = $this->aksi();
        $tren = $this->tren();
        $top = $this->topList();

        $label = 'display:block;margin:0 0 4px;font-size:12px;font-weight:500;opacity:.7';
        $baris = 'display:flex;align-items:center;justify-content:space-between;gap:12px;'
            . 'padding:10px 0;border-bottom:1px solid rgba(128,128,128,.15)';

        /* ---------- Hitung koordinat grafik garis ---------- */
        $w = 860; $h = 280;
        $padL = 42; $padR = 18; $padT = 18; $padB = 40;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;

        $n = count($tren['labels']);
        $maks = max(max($tren['diajukan'] ?: [0]), max($tren['disetujui'] ?: [0]), 1);
        $maks = (int) ceil($maks / 2) * 2;   // bulatkan ke genap agar sumbu rapi

        $xAt = fn ($i) => $n > 1 ? $padL + ($i * $plotW / ($n - 1)) : $padL + $plotW / 2;
        $yAt = fn ($v) => $padT + $plotH - ($v / $maks * $plotH);

        $garis = function (array $data) use ($xAt, $yAt) {
            return collect($data)
                ->map(fn ($v, $i) => round($xAt($i), 1) . ',' . round($yAt($v), 1))
                ->implode(' ');
        };

        $maksTop = $top->max('frekuensi') ?: 1;
    @endphp

    {{-- ==================== KARTU RINGKAS ==================== --}}
    @php
        $kartu = [
            ['Aset Aktif', $r['total_aset'], $r['persen_baik'] . '% kondisi baik', '#14315C'],
            ['Nilai Aset', $this->rupiahRingkas((float) ($r['nilai_aset'] ?? 0)), 'Total perolehan', '#14315C'],
            ['Menunggu Persetujuan', $a['pengajuan_menunggu'] ?? 0, 'Pengajuan aset', '#F79009'],
            ['Perintah Kerja', $a['wo_terbuka'] ?? 0, 'Belum selesai', '#F79009'],
            ['Pinjam Terlambat', $a['pinjam_terlambat'] ?? 0, 'Lewat batas kembali', '#D92D20'],
            ['Jadwal Terlambat', $a['jadwal_terlambat'] ?? 0, 'Pemeliharaan preventif', '#D92D20'],
        ];
    @endphp

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:1rem">
        @foreach ($kartu as [$judul, $nilai, $ket, $hex])
            <x-filament::section compact>
                <p style="margin:0;font-size:12px;font-weight:500;opacity:.65">{{ $judul }}</p>
                <p style="margin:6px 0 0;font-size:{{ is_string($nilai) ? '20px' : '28px' }};
                          font-weight:700;line-height:1.2;
                          color:{{ $nilai === 0 ? 'rgba(128,128,128,.55)' : $hex }}">
                    {{ $nilai }}
                </p>
                <p style="margin:6px 0 0;font-size:11px;opacity:.6">{{ $ket }}</p>
            </x-filament::section>
        @endforeach
    </div>

    {{-- ==================== TREN PENGAJUAN ==================== --}}
    <x-filament::section icon="heroicon-o-chart-bar">
        <x-slot name="heading">Tren Pengajuan Aset</x-slot>
        <x-slot name="description">
            Jumlah pengajuan yang masuk dan yang disetujui per bulan.
        </x-slot>

        <x-slot name="headerEnd">
            <select wire:model.live="trenBulan" class="sapra-input">
                @foreach ($this->periodeOptions() as $nilai => $teks)
                    <option value="{{ $nilai }}">{{ $teks }}</option>
                @endforeach
            </select>
        </x-slot>

        <svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%;height:auto;overflow:visible"
             xmlns="http://www.w3.org/2000/svg">

            {{-- Garis bantu horizontal --}}
            @for ($i = 0; $i <= 4; $i++)
                @php
                    $nilaiY = $maks - ($maks / 4 * $i);
                    $y = $padT + ($plotH / 4 * $i);
                @endphp
                <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $w - $padR }}" y2="{{ $y }}"
                      stroke="rgba(128,128,128,.22)" stroke-width="1"/>
                <text x="{{ $padL - 8 }}" y="{{ $y + 4 }}" text-anchor="end"
                      font-size="11" fill="currentColor" opacity=".55">
                    {{ (int) round($nilaiY) }}
                </text>
            @endfor

            {{-- Garis disetujui --}}
            <polyline points="{{ $garis($tren['disetujui']) }}"
                      fill="none" stroke="#12B76A" stroke-width="2.5"
                      stroke-linejoin="round" stroke-linecap="round"/>

            {{-- Garis diajukan --}}
            <polyline points="{{ $garis($tren['diajukan']) }}"
                      fill="none" stroke="#2E6BE6" stroke-width="2.5"
                      stroke-linejoin="round" stroke-linecap="round"/>

            {{-- Titik dan label sumbu --}}
            @foreach ($tren['labels'] as $i => $teks)
                <circle cx="{{ round($xAt($i), 1) }}" cy="{{ round($yAt($tren['disetujui'][$i]), 1) }}"
                        r="3.5" fill="#12B76A"/>
                <circle cx="{{ round($xAt($i), 1) }}" cy="{{ round($yAt($tren['diajukan'][$i]), 1) }}"
                        r="3.5" fill="#2E6BE6"/>

                <text x="{{ round($xAt($i), 1) }}" y="{{ $h - $padB + 20 }}" text-anchor="middle"
                      font-size="11" fill="currentColor" opacity=".6">{{ $teks }}</text>
            @endforeach
        </svg>

        {{-- Keterangan warna --}}
        <div style="display:flex;justify-content:center;gap:20px;margin-top:10px">
            <span style="display:flex;align-items:center;gap:6px;font-size:12px">
                <span style="width:12px;height:3px;background:#2E6BE6;border-radius:2px"></span>
                Diajukan
            </span>
            <span style="display:flex;align-items:center;gap:6px;font-size:12px">
                <span style="width:12px;height:3px;background:#12B76A;border-radius:2px"></span>
                Disetujui
            </span>
        </div>
    </x-filament::section>

    {{-- ==================== PALING SERING ==================== --}}
    <x-filament::section icon="heroicon-o-fire" icon-color="warning">
        <x-slot name="heading">Paling Sering Diajukan</x-slot>
        <x-slot name="description">Enam bulan terakhir.</x-slot>

        {{-- Pilihan jenis --}}
        <div style="display:flex;gap:18px;margin-bottom:14px">
            <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer">
                <input type="radio" wire:model.live="jenisTop" value="alat">
                Aset / Alat
            </label>
            <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer">
                <input type="radio" wire:model.live="jenisTop" value="habis_pakai">
                Barang Habis Pakai
            </label>
        </div>

        @if ($top->isEmpty())
            <p style="margin:0;font-size:13px;opacity:.6">
                Belum ada data pada periode ini.
            </p>
        @else
            <div>
                @foreach ($top as $item)
                    @php
                        $nama = $jenisTop === 'habis_pakai'
                            ? ($item->consumable?->name ?? '—')
                            : $item->nama;

                        $satuan = $jenisTop === 'habis_pakai'
                            ? ' ' . ($item->consumable?->unit_of_measure ?? '')
                            : '';
                    @endphp

                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="margin:0;font-size:13px;font-weight:600">{{ $nama }}</p>
                            <div style="margin-top:5px;height:5px;border-radius:3px;
                                        background:rgba(128,128,128,.18);overflow:hidden">
                                <div style="height:5px;background:#F79009;
                                            width:{{ round($item->frekuensi / $maksTop * 100) }}%"></div>
                            </div>
                        </div>

                        <div style="text-align:right;flex-shrink:0">
                            <x-filament::badge color="warning">{{ $item->frekuensi }}×</x-filament::badge>
                            <p style="margin:3px 0 0;font-size:11px;opacity:.6">
                                {{ (int) $item->total }}{{ $satuan }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>