@include('errors.layout', [
    'code' => '401',
    'title' => 'Perlu Masuk Dulu',
    'message' => 'Halaman ini hanya bisa diakses setelah Anda masuk ke sistem. Silakan masuk menggunakan akun Anda.',
    'iconBg' => 'bg-amber-50',
    'icon' => '<svg class="h-7 w-7 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>',
    'actions' => '
        <a href="' . url('/asetadmin/login') . '"
           class="block w-full rounded-lg px-4 py-2.5 text-white font-medium text-sm"
           style="background: var(--primary)">Masuk ke Sistem</a>
        <a href="' . url('/') . '"
           class="block w-full rounded-lg px-4 py-2.5 text-slate-600 font-medium text-sm border border-slate-200">Kembali ke Beranda</a>
    ',
])