@push('styles')
    <style>
        .sapra-input {
            padding: 8px 10px;
            font-size: 13px;
            border-radius: 8px;
            border: 1px solid rgba(128, 128, 128, .35);
            background: #fff;
            color: #101828;
            width: 100%;
        }

        .dark .sapra-input {
            background: #1f2937;
            color: #f9fafb;
            border-color: rgba(255, 255, 255, .18);
        }

        .sapra-input option {
            background: #fff;
            color: #101828;
        }

        .dark .sapra-input option {
            background: #1f2937;
            color: #f9fafb;
        }
    </style>
@endpush

<x-filament-panels::page>

    @php
        $loan = $this->loan();
        $label = 'display:block;margin:0 0 4px;font-size:12px;font-weight:500;opacity:.7';
        $grid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px';
        $barisItem =
            'display:flex;align-items:center;justify-content:space-between;gap:12px;' .
            'padding:10px 0;border-bottom:1px solid rgba(128,128,128,.15)';
    @endphp

    {{-- ==================== KOTAK PINDAI ==================== --}}
    <x-filament::section icon="heroicon-o-qr-code">
        <x-slot name="heading">Pindai Kode Alat</x-slot>
        <x-slot name="description">
            Alat yang sedang dipinjam langsung diproses. Alat yang bebas masuk ke daftar peminjaman baru.
        </x-slot>

        <form wire:submit.prevent="pindai" style="display:flex;gap:10px;flex-wrap:wrap">
            <input type="text" wire:model="kode" x-data x-init="$nextTick(() => $el.focus())"
                @fokus-kode.window="$nextTick(() => $el.focus())" placeholder="Contoh: R093FXRR atau PA-26-LAP-0001"
                autocomplete="off" autofocus class="sapra-input"
                style="flex:1;min-width:240px;font-family:monospace;font-size:15px;
                       letter-spacing:1px;text-transform:uppercase">
            <x-filament::button type="submit" icon="heroicon-m-magnifying-glass">Cari</x-filament::button>
        </form>

        @if ($pesan && !$berhasil)
            <div
                style="margin-top:14px;padding:12px 14px;border-radius:8px;
                        background:rgba(217,45,32,.08);border:1px solid rgba(217,45,32,.3)">
                <p style="margin:0;font-size:13px;color:#D92D20">{{ $pesan }}</p>
            </div>
        @endif
    </x-filament::section>

    {{-- ==================== PILIHAN ASET KOLEKTIF ==================== --}}
    @if (!empty($kolektif))
        <x-filament::section icon="heroicon-o-squares-2x2" icon-color="warning">
            <x-slot name="heading">
                {{ $kolektif['code'] }} · {{ $kolektif['name'] }}
            </x-slot>
            <x-slot name="description">
                Aset kolektif, {{ $kolektif['quantity'] }} unit dalam satu kode.
                Pilih peminjaman yang ingin diproses, atau tambahkan ke peminjaman baru.
            </x-slot>

            @if (!empty($kolektif['loans']))
                <div>
                    @foreach ($kolektif['loans'] as $l)
                        <div style="{{ $barisItem }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                            <div style="min-width:0;flex:1">
                                <p style="margin:0;font-size:13px">
                                    <span style="font-family:monospace;font-weight:600">{{ $l['number'] }}</span>
                                    · {{ $l['borrower'] }}
                                    · <strong>{{ $l['jumlah'] }} unit</strong>
                                </p>
                                <p style="margin:2px 0 0;font-size:11px;opacity:.6">
                                    {{ $l['jadwal'] }} · {{ $l['status'] }}
                                </p>
                            </div>

                            <x-filament::button wire:click="prosesPinjaman({{ $l['id'] }})" size="xs"
                                :color="$l['aksi'] === 'serahkan' ? 'info' : 'success'" :icon="$l['aksi'] === 'serahkan' ? 'heroicon-m-arrow-up-tray' : 'heroicon-m-arrow-down-tray'">
                                {{ $l['aksi'] === 'serahkan' ? 'Serahkan' : 'Terima Kembali' }}
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @else
                <p style="margin:0;font-size:13px;opacity:.65">Tidak ada peminjaman berjalan untuk aset ini.</p>
            @endif

            <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
                @if ($kolektif['bisa_pinjam'] && !$kolektif['sudah_di_keranjang'])
                    <x-filament::button wire:click="pinjamKolektif" icon="heroicon-m-plus" color="primary">
                        Tambah ke Peminjaman Baru
                    </x-filament::button>
                @elseif ($kolektif['sudah_di_keranjang'])
                    <p style="margin:0;font-size:12px;opacity:.65;align-self:center">
                        Sudah ada di daftar peminjaman baru. Ubah jumlahnya di sana.
                    </p>
                @endif

                <x-filament::button wire:click="tutupPilihanKolektif" color="gray">Tutup</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{-- ==================== PEMINJAMAN BARU ==================== --}}
    {{-- @if ($aksi === 'baru' && (!empty($keranjang) || !empty($keranjangMassal))) --}}
    {{-- Tampil juga saat masih kosong (mode "Pinjam Alat Massal Saja") --}}
    @if ($aksi === 'baru')

        {{-- Alat ber-QR yang sudah dipindai --}}
        @if (!empty($keranjang))
            <x-filament::section icon="heroicon-o-shopping-cart" icon-color="primary">
                <x-slot name="heading">Alat Dipindai ({{ count($keranjang) }})</x-slot>
                <x-slot name="description">Pindai alat lain untuk menambah ke peminjaman yang sama.</x-slot>

                <div>
                    @foreach ($keranjang as $id => $a)
                        <div style="{{ $barisItem }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                            <div style="min-width:0;flex:1">
                                <p style="margin:0;font-size:13px">
                                    <span style="font-family:monospace;font-weight:600">{{ $a['code'] }}</span>
                                    · {{ $a['name'] }}
                                    @if ($a['loanable_by'] === 'dosen_saja')
                                        <span style="font-size:11px;color:#F79009;font-weight:600">
                                            · Dosen Saja
                                        </span>
                                    @endif
                                </p>
                                <p style="margin:2px 0 0;font-size:11px;opacity:.6">
                                    {{ $a['lab'] }}
                                    @if ($a['collective'] ?? false)
                                        · aset kolektif, {{ $a['max'] }} unit
                                    @endif
                                </p>
                            </div>

                            @if ($a['collective'] ?? false)
                                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                                    <input type="number" min="1" max="{{ $a['max'] }}"
                                        wire:model="keranjang.{{ $id }}.quantity" class="sapra-input"
                                        style="width:90px;text-align:center">
                                    <span style="font-size:11px;opacity:.6">unit</span>
                                </div>
                            @endif

                            <x-filament::button wire:click="hapusDariKeranjang({{ $id }})" color="gray"
                                size="xs" icon="heroicon-m-x-mark">
                                Hapus
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Alat massal, tidak dipindai --}}
        <x-filament::section icon="heroicon-o-beaker" icon-color="info" collapsible :collapsed="empty($keranjangMassal)">
            <x-slot name="heading">
                Alat Massal
                @if (!empty(array_filter($keranjangMassal)))
                    ({{ count(array_filter($keranjangMassal)) }} jenis)
                @endif
            </x-slot>
            <x-slot name="description">
                Gelas ukur, tabung reaksi, dan sejenisnya. Tidak dipindai, cukup ketik jumlahnya.
            </x-slot>

            @php
                $hasilMassal = $this->alatMassalTampil();
                $massal = $hasilMassal['daftar'];
            @endphp

            @if ($hasilMassal['total'] === 0)
                <p style="margin:0;font-size:13px;opacity:.6">
                    Belum ada alat massal terdaftar untuk laboratorium ini.
                </p>
            @else
                {{-- Pencarian: muncul bila alat massal lebih dari 5 --}}
                @if ($hasilMassal['total'] > \App\Filament\Pages\PindaiSerahTerima::MASSAL_AWAL)
                    <div style="margin-bottom:10px">
                        <input type="search" wire:model.live.debounce.300ms="cariMassal"
                            placeholder="Cari nama atau kode alat massal…" class="sapra-input" style="width:100%">
                    </div>
                @endif

                @if ($massal->isEmpty())
                    <p style="margin:0;font-size:13px;opacity:.6">Tidak ada alat massal yang cocok dengan
                        "{{ $cariMassal }}".</p>
                @endif

                <div>
                    @foreach ($massal as $m)
                        <div style="{{ $barisItem }}{{ $loop->last ? ';border-bottom:0' : '' }}">
                            <div style="flex:1;min-width:0">
                                <p style="margin:0;font-size:13px;font-weight:600">{{ $m->name }}</p>
                                <p style="margin:2px 0 0;font-size:11px;opacity:.6">
                                    {{ $m->available_stock }} {{ $m->unit_of_measure }} tersedia
                                </p>
                            </div>

                            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                                <input type="number" min="0" max="{{ $m->available_stock }}"
                                    wire:model="keranjangMassal.{{ $m->id }}" placeholder="0"
                                    class="sapra-input" style="width:90px;text-align:center">
                                <span style="font-size:11px;opacity:.6;min-width:44px">
                                    {{ $m->unit_of_measure }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Peminjam --}}
        <x-filament::section icon="heroicon-o-user" icon-color="info">
            <x-slot name="heading">Peminjam</x-slot>

            @php $p = $this->peminjam(); @endphp

            @if ($p)
                <div
                    style="display:flex;align-items:center;justify-content:space-between;gap:12px;
                            padding:12px 14px;border-radius:8px;
                            background:rgba(46,144,250,.08);border:1px solid rgba(46,144,250,.3)">
                    <div>
                        <p style="margin:0;font-size:14px;font-weight:600">{{ $p->name }}</p>
                        <p style="margin:2px 0 0;font-size:12px;opacity:.7">
                            {{ $p->identity_number ?? '—' }}
                            · {{ $p->roles->pluck('name')->implode(', ') }}
                            · {{ $this->teleponTampil($p->phone) }}
                        </p>
                    </div>

                    <x-filament::button wire:click="$set('userId', null)" color="gray" size="xs">
                        Ganti
                    </x-filament::button>
                </div>
            @else
                <input type="text" wire:model.live.debounce.300ms="cariPeminjam"
                    placeholder="Ketik nama, NIM, atau NIP peminjam" autocomplete="off" class="sapra-input">

                @php $hasil = $this->hasilCariPeminjam(); @endphp

                @if ($hasil->isNotEmpty())
                    <div
                        style="margin-top:10px;max-height:260px;overflow-y:auto;
                                border:1px solid rgba(128,128,128,.2);border-radius:8px">
                        @foreach ($hasil as $u)
                            <button type="button" wire:click="pilihPeminjam({{ $u->id }})"
                                style="display:block;width:100%;text-align:left;padding:10px 12px;
                                       background:transparent;border:0;cursor:pointer;color:inherit;
                                       border-bottom:1px solid rgba(128,128,128,.12)">
                                <span style="font-size:13px;font-weight:600">{{ $u->name }}</span>
                                <span style="font-size:11px;opacity:.65">
                                    · {{ $u->identity_number ?? '—' }}
                                    · {{ $u->roles->pluck('name')->implode(', ') }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen(trim($cariPeminjam)) >= 2)
                    <p style="margin:10px 0 0;font-size:12px;opacity:.6">
                        Tidak ditemukan. Pastikan akun peminjam sudah terdaftar dan aktif.
                    </p>
                @endif
                @if (blank($cariMassal) && $hasilMassal['cocok'] > $massal->count())
                    <p style="margin:10px 0 0;font-size:12px;opacity:.6">
                        Menampilkan {{ $massal->count() }} dari {{ $hasilMassal['total'] }} alat massal.
                        Ketik nama di kotak cari untuk menemukan yang lain.
                    </p>
                @endif
            @endif
        </x-filament::section>

        {{-- Rincian peminjaman --}}
        <x-filament::section icon="heroicon-o-clipboard-document-list">
            <x-slot name="heading">Rincian Peminjaman</x-slot>

            <div style="{{ $grid }}">
                <div>
                    <label style="{{ $label }}">Laboratorium</label>
                    <select wire:model.live="labId" class="sapra-input">
                        <option value="">Umum (di luar lab)</option>
                        @foreach ($this->labOptions() as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="{{ $label }}">Jenis Keperluan</label>
                    <select wire:model.live="purposeType" class="sapra-input">
                        @foreach ($this->keperluanOptions() as $nilai => $teks)
                            <option value="{{ $nilai }}">{{ $teks }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($this->perluMataKuliah())
                    <div>
                        <label style="{{ $label }}">Mata Kuliah</label>
                        <input type="text" wire:model="courseName" class="sapra-input"
                            placeholder="Nama mata kuliah">
                    </div>
                @endif

                @if ($this->perluDosen())
                    <div>
                        <label style="{{ $label }}">Dosen Penanggung Jawab</label>
                        <select wire:model="supervisorId" class="sapra-input">
                            <option value="">— pilih —</option>
                            @foreach ($this->dosenOptions() as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label style="{{ $label }}">Mulai Pinjam</label>
                    <input type="datetime-local" wire:model="startAt" class="sapra-input">
                </div>

                <div>
                    <label style="{{ $label }}">Batas Kembali</label>
                    <input type="datetime-local" wire:model="endAt" class="sapra-input">
                    <p style="margin:4px 0 0;font-size:11px;opacity:.6">{{ $this->batasLabel() }}</p>
                </div>
            </div>

            <div style="margin-top:12px">
                <label style="{{ $label }}">Uraian Keperluan</label>
                <input type="text" wire:model="uraian" class="sapra-input"
                    placeholder="Opsional, misalnya: praktikum jaringan komputer kelas 3A">
            </div>

            <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
                <x-filament::button wire:click="buatPinjaman" wire:loading.attr="disabled"
                    icon="heroicon-m-arrow-up-tray" color="primary">
                    Buat &amp; Serahkan
                </x-filament::button>

                <x-filament::button wire:click="batal" color="gray">Batal</x-filament::button>
            </div>
        </x-filament::section>

    @endif

    {{-- ==================== PINJAMAN BERJALAN ==================== --}}
    @if ($loan)

        {{-- Ringkasan --}}
        <x-filament::section :icon="$aksi === 'serahkan' ? 'heroicon-o-arrow-up-tray' : 'heroicon-o-arrow-down-tray'" :icon-color="$aksi === 'serahkan' ? 'info' : 'success'">
            <x-slot name="heading">
                {{ $aksi === 'serahkan' ? 'Serahkan Alat' : 'Terima Pengembalian' }}
            </x-slot>
            <x-slot name="description">{{ $loan->loan_number }}</x-slot>

            <div style="{{ $grid }}">
                <div>
                    <span style="{{ $label }}">Peminjam</span>
                    <p style="margin:0;font-size:14px;font-weight:600">{{ $loan->borrower_name }}</p>
                    @if ($loan->borrower_identity)
                        <p style="margin:2px 0 0;font-size:12px;opacity:.65">
                            {{ $loan->borrower_identity }}
                        </p>
                    @endif
                </div>

                <div>
                    <span style="{{ $label }}">Laboratorium</span>
                    <p style="margin:0;font-size:14px">{{ $loan->laboratory?->name ?? 'Umum' }}</p>
                </div>

                <div>
                    <span style="{{ $label }}">Jadwal</span>
                    <p style="margin:0;font-size:13px">
                        {{ $loan->start_at->translatedFormat('d M, H:i') }}
                        &ndash; {{ $loan->end_at->translatedFormat('d M, H:i') }}
                    </p>
                </div>

                <div>
                    <span style="{{ $label }}">Status</span>
                    <x-filament::badge :color="$loan->status->getColor()">
                        {{ $loan->status->getLabel() }}
                    </x-filament::badge>
                </div>
            </div>

            @if ($loan->is_overdue)
                <div
                    style="margin-top:14px;padding:10px 12px;border-radius:8px;
                            background:rgba(217,45,32,.08);border:1px solid rgba(217,45,32,.3)">
                    <p style="margin:0;font-size:13px;font-weight:600;color:#D92D20">
                        Terlambat {{ $loan->late_label }}
                    </p>
                </div>
            @endif
        </x-filament::section>

        {{-- Daftar alat --}}
        <x-filament::section>
            <x-slot name="heading">
                Alat dalam Peminjaman
                ({{ $loan->items->count() + $loan->consumableItems->count() }})
            </x-slot>
            <x-slot name="description">
                @if ($aksi === 'kembalikan')
                    Periksa kondisi tiap alat sebelum menerima. Kondisi yang memburuk otomatis
                    dibuatkan laporan kerusakan.
                @else
                    Seluruh alat berikut akan diserahkan sekaligus.
                @endif
            </x-slot>

            {{-- Alat ber-QR --}}
            @if ($loan->items->isNotEmpty())
                <div>
                    @foreach ($loan->items as $item)
                        <div
                            style="padding:14px 0;border-bottom:1px solid rgba(128,128,128,.18)
                                    {{ $loop->last ? ';border-bottom:0' : '' }}">
                            <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:8px">
                                <span style="font-family:monospace;font-size:13px;font-weight:600">
                                    {{ $item->asset?->code ?? '—' }}
                                </span>
                                <span style="font-size:14px">{{ $item->asset?->name ?? '—' }}</span>
                                @if ($item->quantity > 1 || $item->isCollective())
                                    <span style="font-size:13px;font-weight:600">× {{ $item->quantity }} unit</span>
                                @endif
                                @if ($item->condition_before)
                                    <span style="font-size:11px;opacity:.65">
                                        · kondisi saat keluar: {{ $item->condition_before->getLabel() }}
                                    </span>
                                @endif
                            </div>

                            @if ($aksi === 'kembalikan' && $item->isCollective())
                                <div style="{{ $grid }};margin-top:10px">
                                    <div>
                                        <label style="{{ $label }}">Kembali Baik</label>
                                        <input type="number" min="0" max="{{ $item->quantity }}"
                                            wire:model="kolektifKembali.{{ $item->id }}.returned"
                                            class="sapra-input">
                                    </div>

                                    <div>
                                        <label style="{{ $label }}">Rusak</label>
                                        <input type="number" min="0" max="{{ $item->quantity }}"
                                            wire:model="kolektifKembali.{{ $item->id }}.damaged"
                                            class="sapra-input">
                                    </div>

                                    <div>
                                        <label style="{{ $label }}">Catatan</label>
                                        <input type="text" wire:model="catatan.{{ $item->id }}"
                                            placeholder="Selebihnya dianggap hilang" class="sapra-input">
                                    </div>
                                </div>
                            @elseif ($aksi === 'kembalikan')
                                <div style="{{ $grid }};margin-top:10px">
                                    <div>
                                        <label style="{{ $label }}">Kondisi Saat Kembali</label>
                                        <select wire:model="kondisi.{{ $item->id }}" class="sapra-input">
                                            @foreach ($this->kondisiOptions() as $nilai => $teks)
                                                <option value="{{ $nilai }}">{{ $teks }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label style="{{ $label }}">Catatan Kerusakan</label>
                                        <input type="text" wire:model="catatan.{{ $item->id }}"
                                            placeholder="Isi bila ada kerusakan" class="sapra-input">
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Alat massal --}}
            @if ($loan->consumableItems->isNotEmpty())
                <div
                    style="margin-top:{{ $loan->items->isNotEmpty() ? '20px;padding-top:16px;border-top:2px solid rgba(128,128,128,.2)' : '0' }}">
                    <p style="margin:0 0 4px;font-size:13px;font-weight:600">Alat Massal</p>

                    @if ($aksi === 'kembalikan')
                        <p style="margin:0 0 12px;font-size:12px;opacity:.65">
                            Isi jumlah yang kembali utuh dan yang pecah atau hilang.
                            Yang tidak kembali otomatis mengurangi stok.
                        </p>
                    @endif

                    @foreach ($loan->consumableItems as $item)
                        <div
                            style="padding:12px 0;border-bottom:1px solid rgba(128,128,128,.15)
                                    {{ $loop->last ? ';border-bottom:0' : '' }}">
                            <p style="margin:0 0 {{ $aksi === 'kembalikan' ? '8px' : '0' }};font-size:13px">
                                <span style="font-weight:600">{{ $item->consumable?->name ?? '—' }}</span>
                                <span style="opacity:.65">
                                    · {{ $item->quantity }} {{ $item->consumable?->unit_of_measure }}
                                </span>
                            </p>

                            @if ($aksi === 'kembalikan')
                                <div style="{{ $grid }}">
                                    <div>
                                        <label style="{{ $label }}">Kembali Utuh</label>
                                        <input type="number" min="0" max="{{ $item->quantity }}"
                                            wire:model="massalKembali.{{ $item->id }}.returned"
                                            class="sapra-input">
                                    </div>

                                    <div>
                                        <label style="{{ $label }}">Pecah / Hilang</label>
                                        <input type="number" min="0" max="{{ $item->quantity }}"
                                            wire:model="massalKembali.{{ $item->id }}.damaged"
                                            class="sapra-input">
                                    </div>

                                    <div>
                                        <label style="{{ $label }}">Keterangan</label>
                                        <input type="text" wire:model="massalKembali.{{ $item->id }}.note"
                                            placeholder="Isi bila ada yang pecah" class="sapra-input">
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Tombol aksi --}}
            <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
                @if ($aksi === 'serahkan')
                    <x-filament::button wire:click="serahkan" wire:loading.attr="disabled"
                        icon="heroicon-m-arrow-up-tray" color="info">
                        Serahkan &amp; Buat Berita Acara
                    </x-filament::button>
                @else
                    <x-filament::button wire:click="kembalikan" wire:loading.attr="disabled"
                        icon="heroicon-m-arrow-down-tray" color="success">
                        Terima &amp; Buat Berita Acara
                    </x-filament::button>
                @endif

                <x-filament::button wire:click="batal" color="gray">Batal</x-filament::button>

                <x-filament::button tag="a" :href="\App\Filament\Resources\Loans\LoanResource::getUrl('view', ['record' => $loan])" color="gray">
                    Buka Detail
                </x-filament::button>
            </div>
        </x-filament::section>

    @endif

    {{-- ==================== PETUNJUK AWAL ==================== --}}
    @if (!$loan && $aksi !== 'baru' && empty($kolektif))
        <x-filament::section>
            <div style="text-align:center;padding:36px 20px">
                <p style="margin:0;font-size:15px;font-weight:600">Siap memindai</p>
                <p style="margin:8px auto 16px;font-size:13px;opacity:.65;max-width:520px;line-height:1.6">
                    Alat yang sedang dipinjam langsung menampilkan tombol serahkan atau terima kembali.
                    Alat yang bebas dikumpulkan ke daftar, lalu dibuatkan peminjaman baru sekaligus.
                    Aset kolektif seperti kursi lipat menampilkan pilihan: pinjam baru atau proses peminjaman yang
                    berjalan.
                </p>

                <x-filament::button wire:click="mulaiMassal" color="gray" icon="heroicon-m-beaker">
                    Pinjam Alat Massal Saja
                </x-filament::button>

                <p style="margin:10px auto 0;font-size:12px;opacity:.55;max-width:420px">
                    Untuk gelas ukur, tabung reaksi, dan sejenisnya yang tidak berstiker QR.
                </p>
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
