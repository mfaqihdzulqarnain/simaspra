@push('styles')
<style>
    /* ---------- Kontrol ---------- */
    .jr-kontrol { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; }
    .jr-nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .jr-judul { font-size:16px; font-weight:700; margin:0 6px; }
    .jr-kanan { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .jr-select {
        padding:8px 10px; font-size:13px; border-radius:8px; min-width:200px;
        border:1px solid rgba(128,128,128,.35); background:#fff; color:#101828;
    }
    .dark .jr-select { background:#1f2937; color:#f9fafb; border-color:rgba(255,255,255,.18); }

    .jr-toggle { display:inline-flex; border:1px solid rgba(128,128,128,.35); border-radius:8px; overflow:hidden; }
    .jr-toggle button {
        padding:7px 14px; font-size:13px; font-weight:600; background:transparent;
        color:inherit; border:0; cursor:pointer;
    }
    .jr-toggle button + button { border-left:1px solid rgba(128,128,128,.35); }
    .jr-toggle button.aktif { background:#2E90FA; color:#fff; }

    /* ---------- Kegiatan ---------- */
    .jr-acara {
        display:block; padding:6px 8px; border-radius:7px; margin-bottom:5px;
        border-left:3px solid #12B76A; background:rgba(18,183,106,.09);
        color:inherit; text-decoration:none;
    }
    .jr-acara.menunggu { border-left-color:#F79009; border-left-style:dashed; background:rgba(247,144,9,.09); }
    .jr-acara.blok { border-left:3px dotted #98A2B3; background:rgba(152,162,179,.12); opacity:.85; }
    a.jr-acara:hover { filter:brightness(.95); }
    .jr-jam { font-size:12px; font-weight:700; }
    .jr-teks { font-size:12px; margin-top:2px; line-height:1.35; word-break:break-word; }
    .jr-ruang { font-size:11px; opacity:.65; margin-top:2px; }
    .jr-kosong { font-size:12px; opacity:.45; padding:4px 2px; }
    .jr-pelengkap {
        display:inline-block; font-size:10.5px; font-weight:600; margin-top:3px;
        padding:1px 6px; border-radius:999px; background:rgba(46,144,250,.12); color:#1570EF;
    }
    .jb-tanda { color:#1570EF; font-weight:700; margin-left:2px; }

    /* ---------- Tampilan bulan ---------- */
    .jb { border:1px solid rgba(128,128,128,.22); border-radius:12px; overflow:hidden; }
    .jb-kepala, .jb-baris { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); }
    .jb-kepala div {
        padding:8px 10px; font-size:12px; font-weight:600; text-transform:uppercase;
        letter-spacing:.04em; opacity:.65; background:rgba(128,128,128,.05);
        border-bottom:1px solid rgba(128,128,128,.22);
    }
    .jb-sel {
        min-height:118px; padding:6px 7px; border-right:1px solid rgba(128,128,128,.16);
        border-bottom:1px solid rgba(128,128,128,.16); min-width:0;
    }
    .jb-baris .jb-sel:nth-child(7) { border-right:0; }
    .jb-baris:last-child .jb-sel { border-bottom:0; }
    .jb-sel.luar { background:rgba(128,128,128,.04); }
    .jb-sel.luar .jb-angka { opacity:.35; }
    .jb-sel.lewat .jr-acara { opacity:.6; }
    .jb-sel.akhir-pekan { background:rgba(128,128,128,.025); }

    .jb-atas { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
    .jb-angka {
        font-size:13px; font-weight:600; width:26px; height:26px; border-radius:999px;
        display:inline-flex; align-items:center; justify-content:center;
        background:transparent; border:0; color:inherit; cursor:pointer;
    }
    .jb-angka:hover { background:rgba(128,128,128,.12); }
    .hari-ini .jb-angka { background:#2E90FA; color:#fff; }
    .jb-jumlah {
        display:none; font-size:11px; font-weight:700; min-width:20px; padding:1px 6px;
        border-radius:999px; background:#12B76A; color:#fff; text-align:center;
    }

    /* Kegiatan ringkas satu baris di sel bulan */
    .jb-sel .jr-acara { padding:3px 6px; margin-bottom:3px; }
    .jb-ringkas { font-size:11.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .jb-ringkas strong { font-weight:700; }
    .jb-lainnya {
        font-size:11.5px; font-weight:600; color:#2E90FA; background:transparent;
        border:0; padding:2px 4px; cursor:pointer;
    }
    .jb-lainnya:hover { text-decoration:underline; }

    /* Ponsel: sel hanya berisi tanggal dan jumlah kegiatan */
    @media (max-width: 700px) {
        .jb-sel { min-height:58px; padding:4px; }
        .jb-sel .jr-acara, .jb-lainnya { display:none; }
        .jb-jumlah { display:inline-block; }
        .jb-atas { flex-direction:column; gap:3px; align-items:center; }
        .jb-kepala div { padding:6px 2px; text-align:center; font-size:10.5px; }
    }

    /* ---------- Tampilan minggu ---------- */
    .jr-grid { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); gap:10px; }
    @media (max-width: 1100px) { .jr-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px)  { .jr-grid { grid-template-columns:1fr; } }

    .jr-hari {
        border:1px solid rgba(128,128,128,.22); border-radius:12px; padding:10px;
        min-height:150px; background:rgba(128,128,128,.03);
    }
    .jr-hari.hari-ini { border-color:#2E90FA; box-shadow:0 0 0 1px #2E90FA inset; }
    .jr-hari.lewat { opacity:.6; }
    .jr-kepala-hari { display:flex; align-items:baseline; justify-content:space-between; margin-bottom:8px; }
    .jr-nama-hari { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; opacity:.7; }
    .jr-tanggal { font-size:18px; font-weight:700; }
    .jr-hari.hari-ini .jr-tanggal { color:#2E90FA; }

    /* ---------- Legenda ---------- */
    .jr-legenda { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; opacity:.8; margin-top:4px; }
    .jr-legenda span { display:inline-flex; align-items:center; gap:6px; }
    .jr-titik { width:10px; height:10px; border-radius:3px; display:inline-block; }
</style>
@endpush

<x-filament-panels::page>
    {{-- ==================== KONTROL ==================== --}}
    <x-filament::section>
        <div class="jr-kontrol">
            <div class="jr-nav">
                <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="sebelumnya" label="Sebelumnya" color="gray" />
                <x-filament::button wire:click="hariIni" color="gray" size="sm">{{ $this->labelKembali() }}</x-filament::button>
                <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="berikutnya" label="Berikutnya" color="gray" />
                <span class="jr-judul">{{ $this->judulPeriode() }}</span>
            </div>

            <div class="jr-kanan">
                <div class="jr-toggle" role="group" aria-label="Tampilan">
                    <button type="button" wire:click="gantiTampilan('bulan')" @class(['aktif' => $tampilan === 'bulan'])>Bulan</button>
                    <button type="button" wire:click="gantiTampilan('minggu')" @class(['aktif' => $tampilan === 'minggu'])>Minggu</button>
                </div>

                <select wire:model.live="roomId" class="jr-select" aria-label="Pilih ruang">
                    <option value="">Semua ruang</option>
                    @foreach ($this->ruangOptions() as $id => $nama)
                        <option value="{{ $id }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filament::section>

    @if (empty($this->ruangOptions()))
        <x-filament::section>
            <p style="margin:0;font-size:13px;opacity:.7">
                Belum ada ruang yang dibuka untuk reservasi. Aktifkan lewat
                <strong>Data Master → Ruangan</strong>, bagian <strong>Reservasi</strong>.
            </p>
        </x-filament::section>
    @endif

    {{-- ==================== TAMPILAN BULAN ==================== --}}
    @if ($tampilan === 'bulan')
        <div class="jb" wire:loading.class="opacity-60">
            <div class="jb-kepala">
                @foreach ($this->namaHari() as $nama)
                    <div>{{ $nama }}</div>
                @endforeach
            </div>

            @foreach ($this->kalenderBulan() as $baris)
                <div class="jb-baris">
                    @foreach ($baris as $h)
                        <div @class([
                            'jb-sel',
                            'luar' => ! $h['bulan_ini'],
                            'hari-ini' => $h['hari_ini'],
                            'lewat' => $h['lewat'],
                            'akhir-pekan' => $h['tanggal']->isWeekend(),
                        ])>
                            <div class="jb-atas">
                                <button type="button" class="jb-angka"
                                        wire:click="bukaMinggu('{{ $h['tanggal']->toDateString() }}')"
                                        title="Lihat minggu ini">
                                    {{ $h['tanggal']->format('j') }}
                                </button>
                                @if ($h['jumlah'])
                                    <span class="jb-jumlah">{{ $h['jumlah'] }}</span>
                                @endif
                            </div>

                            @foreach ($h['acara'] as $a)
                                @if ($a['url'])
                                    <a href="{{ $a['url'] }}" @class(['jr-acara', 'menunggu' => $a['menunggu'], 'blok' => $a['jenis'] !== 'acara'])
                                       title="{{ ['persiapan' => 'Persiapan: ', 'beres' => 'Beres-beres: '][$a['jenis']] ?? '' }}{{ $a['jam'] }} · {{ $a['judul'] }} · {{ $a['ruang'] }}{{ $a['pelengkap'] ? ' · dengan pelengkap' : '' }}">
                                @else
                                    <div @class(['jr-acara', 'menunggu' => $a['menunggu'], 'blok' => $a['jenis'] !== 'acara'])
                                         title="{{ ['persiapan' => 'Persiapan: ', 'beres' => 'Beres-beres: '][$a['jenis']] ?? '' }}{{ $a['jam'] }} · {{ $a['judul'] }} · {{ $a['ruang'] }}{{ $a['pelengkap'] ? ' · dengan pelengkap' : '' }}">
                                @endif
                                    <div class="jb-ringkas">
                                        @if ($a['jenis'] === 'persiapan')
                                            <strong>Persiapan</strong> {{ $a['judul'] }}
                                        @elseif ($a['jenis'] === 'beres')
                                            <strong>Beres-beres</strong> {{ $a['judul'] }}
                                        @else
                                            <strong>{{ $a['jam_mulai'] }}</strong>
                                            {{ $a['judul'] }}@if (! $roomId) · {{ $a['ruang'] }}@endif @if ($a['pelengkap'])<span class="jb-tanda" aria-label="dengan pelengkap">+</span>@endif
                                        @endif
                                    </div>
                                @if ($a['url'])
                                    </a>
                                @else
                                    </div>
                                @endif
                            @endforeach

                            @if ($h['lainnya'])
                                <button type="button" class="jb-lainnya"
                                        wire:click="bukaMinggu('{{ $h['tanggal']->toDateString() }}')">
                                    +{{ $h['lainnya'] }} lainnya
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    {{-- ==================== TAMPILAN MINGGU ==================== --}}
    @if ($tampilan === 'minggu')
        <div class="jr-grid" wire:loading.class="opacity-60">
            @foreach ($this->hariMinggu() as $h)
                <div @class(['jr-hari', 'hari-ini' => $h['hari_ini'], 'lewat' => $h['lewat']])>
                    <div class="jr-kepala-hari">
                        <span class="jr-nama-hari">{{ $h['tanggal']->locale('id')->translatedFormat('l') }}</span>
                        <span class="jr-tanggal">{{ $h['tanggal']->format('j') }}</span>
                    </div>

                    @forelse ($h['acara'] as $a)
                        @if ($a['url'])
                            <a href="{{ $a['url'] }}" @class(['jr-acara', 'menunggu' => $a['menunggu'], 'blok' => $a['jenis'] !== 'acara'])>
                        @else
                            <div @class(['jr-acara', 'menunggu' => $a['menunggu'], 'blok' => $a['jenis'] !== 'acara'])>
                        @endif
                            <div class="jr-jam">
                                @if ($a['jenis'] === 'persiapan')
                                    Persiapan ·
                                @elseif ($a['jenis'] === 'beres')
                                    Beres-beres ·
                                @endif
                                {{ $a['jam'] }}
                            </div>
                            <div class="jr-teks">{{ $a['judul'] }}</div>
                            @if (! $roomId)
                                <div class="jr-ruang">{{ $a['ruang'] }}</div>
                            @endif
                            @if ($a['pelengkap'] && $a['jenis'] === 'acara')
                                <span class="jr-pelengkap">+ pelengkap</span>
                            @endif
                        @if ($a['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <div class="jr-kosong">Kosong</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    @endif

    <div class="jr-legenda">
        <span><i class="jr-titik" style="background:#12B76A"></i> Disetujui</span>
        <span><i class="jr-titik" style="background:#F79009"></i> Menunggu persetujuan</span>
        <span><i class="jr-titik" style="background:rgba(152,162,179,.45)"></i> Persiapan / beres-beres ruang</span>
        <span><i class="jr-titik" style="border:2px solid #2E90FA"></i> Hari ini</span>
        <span><strong style="color:#1570EF">+</strong> Dengan pelengkap (kursi, sound, dan lainnya)</span>
        @if ($tampilan === 'bulan')
            <span style="opacity:.75">Klik tanggal untuk melihat rincian minggunya.</span>
        @endif
    </div>
</x-filament-panels::page>
