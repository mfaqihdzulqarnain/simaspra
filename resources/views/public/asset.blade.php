@php
    $t = \App\Support\Sapra::theme();
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $asset->name }} — {{ \App\Support\Sapra::appName() }}</title>
    @if ($favicon = \App\Support\Sapra::favicon())
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: {{ $t['primary'] }};
            --soft: {{ $t['soft'] }};
            --accent: {{ $t['accent'] }};
            --bg: {{ $t['bg'] }};
            --border: {{ $t['border'] }};
            --text: {{ $t['text'] }};
            --muted: {{ $t['muted'] }};
        }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
        }

        .qr-box svg {
            width: 100%;
            height: auto;
            display: block;
        }
    </style>
</head>

<body class="min-h-full bg-slate-50 text-slate-900">

    <header class="text-white" style="background: var(--primary)">
        <div class="mx-auto max-w-2xl px-5 py-3 flex items-center justify-between gap-3">
            @if ($logo = \App\Support\Sapra::logoLight())
                <img src="{{ $logo }}" alt="" class="h-9 w-auto bg-white rounded p-1">
            @endif
            <div>
                <p class="font-semibold leading-tight">{{ \App\Support\Sapra::institutionName() }}</p>
                <p class="text-xs opacity-80">Informasi Aset</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('asset.check') }}" class="rounded-lg px-3 py-2 text-xs font-medium"
                    style="border-color: rgba(255,255,255,.3); color: var(--hero-text)">Cek Aset Lain</a>
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-xs font-medium"
                    style="border-color: rgba(255,255,255,.3); color: var(--hero-bg)">Beranda</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-5 py-6 space-y-5">

        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Identitas aset --}}
        <section class="rounded-xl bg-white border border-slate-200 overflow-hidden">
            <div class="p-5 flex gap-5 items-start">
                <div class="shrink-0 w-28 [&>svg]:w-full [&>svg]:h-auto [&>svg]:block">{!! $qr !!}</div>
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Kode Aset</p>
                    <p class="font-mono font-semibold text-lg">{{ $asset->code }}</p>
                    <h1 class="mt-2 text-xl font-bold leading-snug">{{ $asset->name }}</h1>
                    @if ($asset->brand || $asset->model)
                        <p class="text-sm text-slate-600">{{ trim($asset->brand . ' ' . $asset->model) }}</p>
                    @endif
                </div>
            </div>

            {{-- @if ($foto = $asset->getFirstMediaUrl('foto'))
                <img src="{{ $foto }}" alt=""
                    class="w-full h-52 object-contain border-t border-slate-200">
            @endif --}}
            @if ($foto = $asset->getFirstMediaUrl('foto'))
                <div class="border-t" style="border-color: var(--border); background: var(--soft)">
                    <img src="{{ $foto }}" alt=""
                        onclick="document.getElementById('lightbox').style.display='flex'"
                        class="w-full h-56 object-contain cursor-zoom-in">
                </div>

                <div id="lightbox" onclick="this.style.display='none'"
                    style="display:none;position:fixed;inset:0;z-index:50;background:rgba(16,24,40,.9);
                        align-items:center;justify-content:center;padding:20px;cursor:zoom-out">
                    <img src="{{ $foto }}" alt=""
                        style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px">
                </div>
            @endif
        </section>

        {{-- Status --}}
        <section class="rounded-xl bg-white border border-slate-200 p-5">
            <div class="flex flex-wrap gap-2 mb-4">
                <span
                    class="rounded-full px-3 py-1 text-xs font-medium
                {{ $asset->condition->getColor() === 'success' ? 'bg-emerald-100 text-emerald-800' : '' }}
                {{ $asset->condition->getColor() === 'warning' ? 'bg-amber-100 text-amber-800' : '' }}
                {{ $asset->condition->getColor() === 'danger' ? 'bg-red-100 text-red-800' : '' }}">
                    {{ $asset->condition->getLabel() }}
                </span>
                <span class="rounded-full px-3 py-1 text-xs font-medium bg-slate-100 text-slate-700">
                    {{ $asset->status->getLabel() }}
                </span>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Kategori</dt>
                    <dd class="font-medium">{{ $asset->category?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Unit Pemilik</dt>
                    <dd class="font-medium">{{ $asset->unit?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Lokasi</dt>
                    <dd class="font-medium">{{ $asset->room?->full_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Tahun Perolehan</dt>
                    <dd class="font-medium">{{ $asset->year_acquired }}</dd>
                </div>

                @if ($isInternal)
                    <div>
                        <dt class="text-slate-500">Nilai Perolehan</dt>
                        <dd class="font-medium">Rp {{ number_format((float) $asset->acquisition_value, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Nomor Seri</dt>
                        <dd class="font-medium font-mono">{{ $asset->serial_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Garansi</dt>
                        <dd class="font-medium">
                            {{ $asset->warranty_until?->translatedFormat('d M Y') ?? '—' }}
                            @if ($asset->isUnderWarranty())
                                <span class="text-emerald-600">(aktif)</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Vendor</dt>
                        <dd class="font-medium">{{ $asset->vendor?->name ?? '—' }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- Panel opname --}}
        @if ($opname)
            <section class="rounded-xl bg-white border-2 p-5" style="border-color: var(--primary)">
                <div class="flex items-start justify-between gap-3 mb-1">
                    <div>
                        <h2 class="font-semibold" style="color: var(--primary)">Pencatatan Opname</h2>
                        <p class="text-xs text-slate-500">{{ $opname->name }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-sky-100 text-sky-800 px-3 py-1 text-xs font-medium">
                        Berjalan
                    </span>
                </div>

                @if (session('opname_status'))
                    <div
                        class="mt-3 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
                        {{ session('opname_status') }}
                    </div>
                @endif

                @if (session('opname_error'))
                    <div class="mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        {{ session('opname_error') }}
                    </div>
                @endif

                {{-- Status pemeriksaan sebelumnya --}}
                @if ($opnameItem?->checked_at)
                    <div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm">
                        <p class="text-slate-600">
                            Sudah diperiksa
                            {{ $opnameItem->checked_at->diffForHumans() }}
                            oleh {{ $opnameItem->checker?->name ?? '—' }}
                        </p>
                        <p class="mt-1 font-medium">
                            Hasil: {{ $opnameItem->status->getLabel() }}
                            @if ($opnameItem->quantity_label)
                                · {{ $opnameItem->quantity_label }}
                            @endif
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Mengisi ulang akan menimpa hasil sebelumnya.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('asset.public.opname', $asset->public_code) }}"
                    class="mt-4 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium mb-1">Ruang Tempat Ditemukan</label>
                        <select name="found_room_id"
                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                            <option value="">— Pilih ruang —</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('found_room_id', $opnameItem?->found_room_id ?? $asset->room_id) == $room->id)>
                                    {{ $room->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @if ($asset->room)
                            <p class="text-xs text-slate-500 mt-1">Tercatat di: {{ $asset->room->full_name }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Kondisi Fisik</label>
                        <select name="condition" class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                            @foreach (\App\Enums\AssetCondition::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('condition', ($opnameItem?->condition_found ?? $asset->condition)->value) === $case->value)>
                                    {{ $case->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Tercatat: {{ $asset->condition->getLabel() }}</p>
                    </div>

                    @if ($asset->isCollective())
                        @php
                            $harapan = $opnameItem?->expected_quantity ?? $asset->quantity;
                            $dipinjam = \App\Services\OpnameService::unitsOnLoan($asset);
                        @endphp
                        <div>
                            <label class="block text-sm font-medium mb-1">Jumlah Ditemukan</label>
                            <input type="number" name="found_quantity" min="0" inputmode="numeric" required
                                value="{{ old('found_quantity', $opnameItem?->found_quantity ?? $harapan) }}"
                                class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                            <p class="text-xs text-slate-500 mt-1">
                                Tercatat {{ $harapan }} unit.
                                @if ($dipinjam)
                                    Sedang dipinjam {{ $dipinjam }} unit, ikut hitung sebagai ditemukan.
                                @endif
                            </p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium mb-1">Catatan</label>
                        <textarea name="note" rows="2" maxlength="500" placeholder="Opsional"
                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">{{ old('note', $opnameItem?->note) }}</textarea>
                    </div>

                    <button type="submit" class="w-full rounded-lg px-4 py-2.5 text-white font-medium text-sm"
                        style="background: var(--primary)">
                        Catat Ditemukan
                    </button>
                </form>

                {{-- Tombol terpisah agar tidak salah klik --}}
                <form method="POST" action="{{ route('asset.public.opname', $asset->public_code) }}"
                    class="mt-2">
                    @csrf
                    <input type="hidden" name="not_found" value="1">
                    <button type="submit" onclick="return confirm('Tandai aset ini tidak ditemukan?')"
                        class="w-full rounded-lg px-4 py-2.5 text-red-700 font-medium text-sm border border-red-200 bg-red-50">
                        Tandai Tidak Ditemukan
                    </button>
                </form>
            </section>
        @elseif ($isInternal)
            {{-- Petunjuk bila tidak ada opname berjalan --}}
            <section class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                <p class="text-sm font-medium text-slate-700">Tidak ada opname berjalan</p>
                <p class="text-xs text-slate-500 mt-1">
                    Aset ini tidak tercakup dalam periode inventarisasi yang sedang aktif.
                    Buka menu Stock Opname di panel admin untuk memulai kegiatan.
                </p>
                <a href="{{ url('/asetadmin/stock-opnames') }}" class="inline-block mt-3 text-sm font-medium"
                    style="color: var(--primary)">
                    Buka Stock Opname
                </a>
            </section>
        @endif

        {{-- Lapor kerusakan --}}
        @if ($canReport && !$asset->status->isRetired())
            <section class="rounded-xl bg-white border border-slate-200 p-5">
                <h2 class="font-semibold mb-1">Laporkan Kerusakan</h2>
                <p class="text-sm text-slate-600 mb-4">Isi bila aset ini rusak atau tidak berfungsi.</p>

                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('asset.public.report', $asset->public_code) }}"
                    class="space-y-4">
                    @csrf

                    {{-- honeypot: disembunyikan dari manusia, diisi bot --}}
                    <input type="text" name="website" tabindex="-1" autocomplete="off"
                        class="absolute opacity-0 h-0 w-0 pointer-events-none" aria-hidden="true">

                    <div>
                        <label class="block text-sm font-medium mb-1">Nama Pelapor <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="reporter_name" required maxlength="100"
                            value="{{ old('reporter_name', auth()->user()?->name) }}"
                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Kontak (HP/Email)</label>
                        <input type="text" name="reporter_contact" maxlength="60"
                            value="{{ old('reporter_contact') }}"
                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Tingkat Kerusakan <span
                                class="text-red-500">*</span></label>
                        <select name="severity" required
                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                            @foreach (\App\Enums\DamageSeverity::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('severity') === $case->value)>
                                    {{ $case->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Keterangan <span
                                class="text-red-500">*</span></label>
                        <textarea name="description" rows="3" required minlength="10" maxlength="1000"
                            placeholder="Jelaskan kerusakan yang terjadi" class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">{{ old('description') }}</textarea>
                    </div>

                    <button type="submit" class="w-full rounded-lg px-4 py-2.5 text-white font-medium text-sm"
                        style="background: var(--primary)">
                        Kirim Laporan
                    </button>
                </form>
            </section>
        @endif

        <p class="text-center text-xs pb-6" style="color: var(--muted)">
            <a href="{{ route('asset.check') }}" class="hover:underline">Cek aset lain</a>
            <span class="mx-1">·</span>
            <a href="{{ route('home') }}" class="hover:underline">Beranda</a>
            <span class="mx-1">·</span>
            {{ \App\Support\Sapra::institutionName() }}
        </p>
    </main>

</body>

</html>
