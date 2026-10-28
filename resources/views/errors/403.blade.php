@php
    // Beranda sesuai hak akses (homeUrl panel): Beranda utama, Beranda Lab, atau Beranda pribadi
    try {
        $berandaUrl = auth()->check()
            ? \Filament\Facades\Filament::getPanel('asetadmin')->getHomeUrl()
            : url('/asetadmin/login');
    } catch (\Throwable) {
        $berandaUrl = url('/');
    }
@endphp

@include('errors.layout', [
    'code' => '403',
    'title' => 'Akses Ditolak',
    'message' => 'Akun Anda tidak memiliki izin untuk membuka halaman ini. Hubungi Bagian Aset & Sarana Prasarana bila Anda merasa seharusnya punya akses.',
    'iconBg' => 'bg-red-50',
    'icon' => '<svg class="h-7 w-7 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>',
    'actions' => '
        <a href="' . e($berandaUrl) . '"
           class="block w-full rounded-lg px-4 py-2.5 text-white font-medium text-sm"
           style="background: var(--primary)">Kembali ke Beranda</a>
    ',
])