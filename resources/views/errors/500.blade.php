@include('errors.layout', [
    'code' => '500',
    'title' => 'Terjadi Kesalahan',
    'message' => 'Sistem mengalami gangguan saat memproses permintaan Anda. Silakan coba beberapa saat lagi atau hubungi administrator.',
    'iconBg' => 'bg-red-50',
    'icon' => '<svg class="h-7 w-7 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.008v.008H12v-.008Z"/></svg>',
    'actions' => '
        <a href="' . url('/') . '"
           class="block w-full rounded-lg px-4 py-2.5 text-white font-medium text-sm"
           style="background: var(--primary)">Kembali ke Beranda</a>
    ',
])