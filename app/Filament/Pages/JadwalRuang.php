<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Scopes\RoomBookingVisibilityScope;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Kalender pemakaian ruang, tampilan bulan atau minggu.
 *
 * Tidak punya menu sendiri: dibuka lewat tombol "Lihat Jadwal" di
 * Reservasi Ruang, dan menu Reservasi Ruang tetap tersorot saat halaman
 * ini terbuka (lihat RoomBookingResource::getNavigationItemActiveRoutePattern).
 *
 * Semua pengguna yang boleh melihat Reservasi Ruang bisa membuka halaman ini
 * dan melihat jam terisi di seluruh ruang. Yang tampil hanya nama kegiatan
 * dan jam; rincian pemesan hanya bisa dibuka oleh yang berhak.
 */
class JadwalRuang extends Page
{
    /** Satu menu saja: Reservasi Ruang. Halaman ini bagian dari menu itu. */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Jadwal Ruang';

    protected string $view = 'filament.pages.jadwal-ruang';

    /** Batas kegiatan yang ditampilkan per sel pada tampilan bulan */
    public const MAKS_PER_SEL = 3;

    /** 'bulan' atau 'minggu' */
    public string $tampilan = 'bulan';

    /** Tanggal acuan, format Y-m-d. Bulan dan minggu yang tampil dihitung dari sini. */
    public string $tanggal = '';

    public ?int $roomId = null;

    /** Mengikuti izin melihat daftar Reservasi Ruang (RoomBookingPolicy::viewAny) */
    public static function canAccess(): bool
    {
        return RoomBookingResource::canViewAny();
    }

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();

        // Dibuka dari tautan ruang tertentu: ?ruang=12&tanggal=2026-10-06&tampilan=minggu
        $this->roomId = request()->integer('ruang') ?: null;

        if (in_array(request()->query('tampilan'), ['bulan', 'minggu'], true)) {
            $this->tampilan = request()->query('tampilan');
        }

        if ($tanggal = request()->query('tanggal')) {
            try {
                $this->tanggal = Carbon::parse($tanggal)->toDateString();
            } catch (\Throwable) {
                // Abaikan tanggal yang tidak valid
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buat')
                ->label('Buat Reservasi')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->url(fn () => RoomBookingResource::getUrl('create'))
                ->visible(fn () => RoomBookingResource::canCreate()),

            Action::make('daftar')
                ->label('Daftar Reservasi')
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(fn () => RoomBookingResource::getUrl('index')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            RoomBookingResource::getUrl('index') => 'Reservasi Ruang',
            'Jadwal',
        ];
    }

    /* ----------------------------- Navigasi ------------------------------- */

    public function sebelumnya(): void
    {
        $acuan = Carbon::parse($this->tanggal);

        $this->tanggal = ($this->tampilan === 'bulan'
            ? $acuan->startOfMonth()->subMonthNoOverflow()
            : $acuan->subWeek())->toDateString();
    }

    public function berikutnya(): void
    {
        $acuan = Carbon::parse($this->tanggal);

        $this->tanggal = ($this->tampilan === 'bulan'
            ? $acuan->startOfMonth()->addMonthNoOverflow()
            : $acuan->addWeek())->toDateString();
    }

    public function hariIni(): void
    {
        $this->tanggal = now()->toDateString();
    }

    public function gantiTampilan(string $tampilan): void
    {
        if (in_array($tampilan, ['bulan', 'minggu'], true)) {
            $this->tampilan = $tampilan;
        }
    }

    /** Dari sel bulan: buka minggu yang memuat tanggal itu */
    public function bukaMinggu(string $tanggal): void
    {
        $this->tanggal = Carbon::parse($tanggal)->toDateString();
        $this->tampilan = 'minggu';
    }

    /* ------------------------------- Label -------------------------------- */

    public function ruangOptions(): array
    {
        return Room::query()
            ->bookable()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function judulPeriode(): string
    {
        $acuan = Carbon::parse($this->tanggal)->locale('id');

        if ($this->tampilan === 'bulan') {
            return $acuan->translatedFormat('F Y');
        }

        $awal = $acuan->copy()->startOfWeek(Carbon::MONDAY);
        $akhir = $awal->copy()->addDays(6);

        return $awal->isSameMonth($akhir)
            ? $awal->format('j').'–'.$akhir->translatedFormat('j F Y')
            : $awal->translatedFormat('j M').' – '.$akhir->translatedFormat('j M Y');
    }

    public function labelKembali(): string
    {
        return $this->tampilan === 'bulan' ? 'Bulan Ini' : 'Minggu Ini';
    }

    /** @return array<string> */
    public function namaHari(): array
    {
        return ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    }

    /* ------------------------------- Data --------------------------------- */

    /**
     * Tampilan bulan: baris per minggu, Senin sampai Minggu.
     * Hari di luar bulan tetap ditampilkan samar agar kotak selalu penuh.
     *
     * @return array<int, array<int, array>>
     */
    public function kalenderBulan(): array
    {
        $acuan = Carbon::parse($this->tanggal);
        $awalBulan = $acuan->copy()->startOfMonth();

        $awal = $awalBulan->copy()->startOfWeek(Carbon::MONDAY);
        $akhir = $acuan->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->addSecond()->startOfDay();

        $reservasi = $this->ambilReservasi($awal, $akhir);

        $minggu = [];
        $hari = $awal->copy();

        while ($hari->lt($akhir)) {
            $baris = [];

            for ($i = 0; $i < 7; $i++) {
                $acara = $this->acaraPadaHari($reservasi, $hari);
                $jumlahKegiatan = count(array_filter($acara, fn ($a) => $a['jenis'] === 'acara'));

                $baris[] = [
                    'tanggal' => $hari->copy(),
                    'bulan_ini' => $hari->isSameMonth($awalBulan),
                    'hari_ini' => $hari->isToday(),
                    'lewat' => $hari->lt(now()->startOfDay()),
                    'acara' => array_slice($acara, 0, static::MAKS_PER_SEL),
                    'lainnya' => max(count($acara) - static::MAKS_PER_SEL, 0),
                    'jumlah' => $jumlahKegiatan,
                ];

                $hari->addDay();
            }

            $minggu[] = $baris;
        }

        return $minggu;
    }

    /**
     * Tampilan minggu: tujuh hari dengan seluruh kegiatannya.
     *
     * @return array<int, array>
     */
    public function hariMinggu(): array
    {
        $awal = Carbon::parse($this->tanggal)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $akhir = $awal->copy()->addDays(7);

        $reservasi = $this->ambilReservasi($awal, $akhir);
        $hasil = [];

        for ($i = 0; $i < 7; $i++) {
            $hari = $awal->copy()->addDays($i);

            $hasil[] = [
                'tanggal' => $hari,
                'hari_ini' => $hari->isToday(),
                'lewat' => $hari->lt(now()->startOfDay()),
                'acara' => $this->acaraPadaHari($reservasi, $hari),
            ];
        }

        return $hasil;
    }

    /**
     * Reservasi yang menempati ruang pada rentang waktu, tanpa scope visibilitas.
     * Rentang diperlebar 3 hari (batas jeda terpanjang) agar hari persiapan
     * dan beres-beres di tepi periode ikut tampil.
     */
    protected function ambilReservasi(Carbon $awal, Carbon $akhir): Collection
    {
        return RoomBooking::query()
            ->withoutGlobalScope(RoomBookingVisibilityScope::class)
            ->occupying()
            ->overlapping($awal->copy()->subDays(3), $akhir->copy()->addDays(3))
            ->when($this->roomId, fn ($q) => $q->where('room_id', $this->roomId))
            ->with('room:id,name,pic_user_id,booking_setup_minutes,booking_cleanup_minutes')
            ->orderBy('start_at')
            ->get();
    }

    /**
     * Kegiatan pada satu hari, beserta blok persiapan dan beres-beres ruang.
     * Kegiatan lintas hari dipotong sesuai hari itu.
     */
    protected function acaraPadaHari(Collection $reservasi, Carbon $hari): array
    {
        $mulaiHari = $hari->copy()->startOfDay();
        $akhirHari = $hari->copy()->endOfDay();

        $user = Auth::user();
        $lihatSemua = $user?->canSeeAllUnits() ?? false;
        $userId = (int) $user?->getKey();

        $potong = function (Carbon $dari, Carbon $sampai) use ($mulaiHari, $akhirHari): ?array {
            if (! ($dari->lt($akhirHari) && $sampai->gt($mulaiHari))) {
                return null;
            }

            return [
                $dari->lt($mulaiHari) ? '00.00' : $dari->format('H.i'),
                $sampai->gt($akhirHari) ? '24.00' : $sampai->format('H.i'),
            ];
        };

        $hasil = [];

        foreach ($reservasi as $b) {
            $bisaBuka = $lihatSemua
                || (int) $b->user_id === $userId
                || (int) $b->created_by === $userId
                || (int) $b->room?->pic_user_id === $userId;

            $dasar = [
                'judul' => $b->title,
                'ruang' => $b->room?->name ?? '—',
                'menunggu' => $b->status === BookingStatus::Diajukan,
                'pelengkap' => $b->loan_id !== null,
                'url' => $bisaBuka ? RoomBookingResource::getUrl('view', ['record' => $b]) : null,
            ];

            $blok = [
                'persiapan' => [$b->start_at->copy()->subMinutes($b->room?->setupMinutes() ?? 0), $b->start_at],
                'acara' => [$b->start_at, $b->end_at],
                'beres' => [$b->end_at, $b->end_at->copy()->addMinutes($b->room?->cleanupMinutes() ?? 0)],
            ];

            foreach ($blok as $jenis => [$dari, $sampai]) {
                if ($dari->gte($sampai)) {
                    continue;
                }

                $jam = $potong($dari, $sampai);

                if (! $jam) {
                    continue;
                }

                $hasil[] = $dasar + [
                    'jenis' => $jenis,
                    'jam_mulai' => $jam[0],
                    'jam' => $jam[0].'–'.$jam[1],
                    'urut' => $dari->timestamp,
                ];
            }
        }

        return collect($hasil)->sortBy('urut')->values()->all();
    }
}
