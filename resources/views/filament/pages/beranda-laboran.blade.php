<x-filament-panels::page>

    @php
        $s = $this->stats();
        $labs = $this->laboratories();
        $terlambat = $this->terlambatList();
        $menunggu = $this->menungguList();
        $siap = $this->siapAmbilList();
        $tempo = $this->jatuhTempoList();

        $loanUrl = fn ($l) => \App\Filament\Resources\Loans\LoanResource::getUrl('view', ['record' => $l]);
        $kodeAlat = fn ($l) => $l->items->pluck('asset.code')->filter()->implode(', ') ?: '—';

        $baris = 'display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;'
            . 'gap:12px;padding:12px 0;border-bottom:1px solid rgba(128,128,128,.18)';
        $judul = 'margin:0;font-size:14px;font-weight:600';
        $sub = 'margin:2px 0 0;font-size:12px;opacity:.65';
        $aksi = 'display:flex;gap:8px;flex-shrink:0';
    @endphp

    {{-- Lab yang diampu --}}
    @if ($labs->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            @foreach ($labs as $lab)
                <x-filament::badge color="primary" icon="heroicon-m-beaker">
                    {{ $lab->name }}
                </x-filament::badge>
            @endforeach
        </div>
    @else
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">Belum ditugaskan ke laboratorium</x-slot>
            <x-slot name="description">
                Hubungi Bagian Sarana dan Prasarana untuk penugasan.
            </x-slot>
        </x-filament::section>
    @endif

    {{-- Kartu ringkas --}}
    @php
        $kartu = [
            ['Menunggu', $s['menunggu'], 'Perlu disetujui', 'warning', '#F79009'],
            ['Siap Diambil', $s['siap_ambil'], 'Sudah disetujui', 'info', '#2E90FA'],
            ['Dipinjam', $s['dipinjam'], 'Sedang di luar', 'primary', '#14315C'],
            ['Jatuh Tempo', $s['hari_ini'], 'Kembali hari ini', 'warning', '#F79009'],
            ['Terlambat', $s['terlambat'], 'Perlu ditagih', 'danger', '#D92D20'],
            ['Kerusakan', $s['kerusakan'], 'Belum ditindak', 'danger', '#D92D20'],
        ];
    @endphp

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:1rem">
        @foreach ($kartu as [$label, $nilai, $ket, $warna, $hex])
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

    {{-- Terlambat --}}
    @if ($terlambat->isNotEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">Terlambat Dikembalikan</x-slot>
            <x-slot name="description">Hubungi peminjam lewat WhatsApp.</x-slot>

            <div>
                @foreach ($terlambat as $l)
                    @php
                        $wa = \App\Support\Phone::waLink(
                            $l->borrower_phone,
                            \App\Services\ReminderService::compose($l)
                        );
                    @endphp

                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="{{ $judul }}">
                                {{ $l->borrower_name }}
                                @if ($l->reminders_count > 0)
                                    <span style="font-size:11px;font-weight:500;opacity:.7">
                                        · {{ $l->reminders_count }}× diingatkan
                                    </span>
                                @endif
                            </p>
                            <p style="{{ $sub }}">
                                {{ $l->loan_number }}
                                · {{ $kodeAlat($l) }}
                                · {{ $l->laboratory?->name ?? 'Umum' }}
                            </p>
                            <p style="margin:4px 0 0;font-size:12px;font-weight:600;color:#D92D20">
                                Terlambat {{ $l->late_label }}
                                <span style="font-weight:400;opacity:.75">
                                    · batas {{ $l->end_at->translatedFormat('d M, H:i') }}
                                </span>
                            </p>
                        </div>

                        <div style="{{ $aksi }}">
                            @if ($wa)
                                <x-filament::button
                                    tag="a"
                                    :href="$wa"
                                    target="_blank"
                                    color="danger"
                                    size="xs"
                                    icon="heroicon-m-chat-bubble-left-right"
                                    wire:click="catatPengingat({{ $l->id }})"
                                >
                                    Ingatkan
                                </x-filament::button>
                            @else
                                <span style="font-size:12px;opacity:.55">Nomor tidak valid</span>
                            @endif

                            <x-filament::button tag="a" :href="$loanUrl($l)" color="gray" size="xs">
                                Lihat
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Menunggu persetujuan --}}
    @if ($menunggu->isNotEmpty())
        <x-filament::section icon="heroicon-o-clock" icon-color="warning">
            <x-slot name="heading">Menunggu Persetujuan</x-slot>

            <div>
                @foreach ($menunggu as $l)
                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="{{ $judul }}">{{ $l->borrower_name }}</p>
                            <p style="{{ $sub }}">
                                {{ $l->loan_number }}
                                · {{ $kodeAlat($l) }}
                                · {{ $l->laboratory?->name ?? 'Umum' }}
                            </p>
                            <p style="{{ $sub }}">
                                {{ $l->start_at->translatedFormat('d M, H:i') }}
                                &ndash; {{ $l->end_at->translatedFormat('d M, H:i') }}
                            </p>
                        </div>

                        <div style="{{ $aksi }}">
                            <x-filament::button tag="a" :href="$loanUrl($l)" size="xs">
                                Proses
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Siap diambil --}}
    @if ($siap->isNotEmpty())
        <x-filament::section icon="heroicon-o-arrow-up-tray" icon-color="info" collapsible>
            <x-slot name="heading">Siap Diambil</x-slot>
            <x-slot name="description">Sudah disetujui, menunggu peminjam datang.</x-slot>

            <div>
                @foreach ($siap as $l)
                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="{{ $judul }}">{{ $l->borrower_name }}</p>
                            <p style="{{ $sub }}">
                                {{ $l->loan_number }}
                                · {{ $kodeAlat($l) }}
                                · mulai {{ $l->start_at->translatedFormat('d M, H:i') }}
                            </p>
                        </div>

                        <div style="{{ $aksi }}">
                            <x-filament::button tag="a" :href="$loanUrl($l)" color="gray" size="xs">
                                Serahkan
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Jatuh tempo hari ini --}}
    @if ($tempo->isNotEmpty())
        <x-filament::section icon="heroicon-o-calendar-days" icon-color="warning" collapsible collapsed>
            <x-slot name="heading">Jatuh Tempo Hari Ini</x-slot>

            <div>
                @foreach ($tempo as $l)
                    <div style="{{ $baris }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                        <div style="min-width:0;flex:1">
                            <p style="{{ $judul }}">{{ $l->borrower_name }}</p>
                            <p style="{{ $sub }}">
                                {{ $l->loan_number }}
                                · {{ $kodeAlat($l) }}
                                · kembali pukul {{ $l->end_at->format('H:i') }}
                            </p>
                        </div>

                        <div style="{{ $aksi }}">
                            <x-filament::button tag="a" :href="$loanUrl($l)" color="gray" size="xs">
                                Lihat
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Kosong --}}
    @if ($terlambat->isEmpty() && $menunggu->isEmpty() && $siap->isEmpty() && $tempo->isEmpty())
        <x-filament::section>
            <div style="text-align:center;padding:40px 20px">
                <p style="margin:0;font-size:15px;font-weight:600">Tidak ada yang perlu ditangani</p>
                <p style="margin:6px 0 0;font-size:13px;opacity:.65">
                    Semua peminjaman berjalan sesuai jadwal.
                </p>
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>