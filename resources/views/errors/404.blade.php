@include('errors.layout', [
    'code' => '404',
    'title' => 'Tidak Ditemukan',
    'message' => 'Data yang Anda cari tidak ada atau sudah dihapus. Bila Anda memindai stiker QR, kemungkinan aset tersebut belum terdaftar di sistem.',
    'iconBg' => 'bg-slate-100',
    'icon' => '<svg class="h-7 w-7 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>',
    'actions' => '
        <a href="' . url('/') . '"
           class="block w-full rounded-lg px-4 py-2.5 text-white font-medium text-sm"
           style="background: var(--primary)">Kembali ke Beranda</a>
    ',
])