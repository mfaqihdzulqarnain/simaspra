<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Loan;
use App\Models\RoomBooking;
use App\Models\WorkOrder;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Beranda pribadi untuk peran yang tidak melihat Beranda utama maupun
 * Beranda Lab: dosen, mahasiswa, teknisi, staf, kaprodi, peminjam eksternal.
 *
 * Isinya hanya data milik pengguna sendiri. Tidak ada angka kampus,
 * nilai aset, atau data lab yang dibatasi untuk Sarpras dan laboran.
 */
class BerandaSaya extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Beranda';

    protected static ?string $title = 'Beranda';

    protected static ?string $slug = 'beranda-saya';

    protected static ?int $navigationSort = -2;

    protected string $view = 'filament.pages.beranda-saya';

    use HasPageShield {
        canAccess as protected shieldCanAccess;
    }

    /** Semua pengguna yang login boleh membuka beranda pribadinya */
    public static function canAccess(): bool
    {
        return Auth::check();
    }

    /** Menu hanya tampil bagi yang tidak punya beranda lain */
    public static function shouldRegisterNavigation(): bool
    {
        return ! Dashboard::canView() && ! BerandaLaboran::canAccess();
    }

    /* ------------------------------- Data --------------------------------- */

    protected function pinjamanSaya()
    {
        return Loan::query()->where('user_id', Auth::id());
    }

    public function stats(): array
    {
        $kini = now();

        return [
            'menunggu' => $this->pinjamanSaya()->where('status', LoanStatus::Diajukan)->count(),
            'siap' => $this->pinjamanSaya()->where('status', LoanStatus::Disetujui)->count(),
            'dipinjam' => $this->pinjamanSaya()->where('status', LoanStatus::Dipinjam)->count(),
            'terlambat' => $this->pinjamanSaya()->where('status', LoanStatus::Terlambat)->count(),
            'reservasi' => RoomBooking::query()
                ->where(fn ($q) => $q->where('user_id', Auth::id())->orWhere('created_by', Auth::id()))
                ->occupying()
                ->where('end_at', '>=', $kini)
                ->count(),
            'tugas' => $this->punyaTugasTeknisi()
                ? WorkOrder::query()
                    ->where('assigned_to', Auth::id())
                    ->whereIn('status', [WorkOrderStatus::Ditugaskan->value, WorkOrderStatus::Dikerjakan->value])
                    ->count()
                : null,
        ];
    }

    /** Pinjaman yang masih berjalan atau menunggu, terdekat batas kembalinya dulu */
    public function pinjamanBerjalan(): Collection
    {
        return $this->pinjamanSaya()
            ->whereIn('status', [
                LoanStatus::Terlambat->value,
                LoanStatus::Dipinjam->value,
                LoanStatus::Disetujui->value,
                LoanStatus::Diajukan->value,
            ])
            ->with(['items.asset:id,code,name', 'laboratory:id,name'])
            ->orderBy('end_at')
            ->limit(6)
            ->get();
    }

    public function reservasiMendatang(): Collection
    {
        return RoomBooking::query()
            ->where(fn ($q) => $q->where('user_id', Auth::id())->orWhere('created_by', Auth::id()))
            ->whereIn('status', [BookingStatus::Diajukan->value, BookingStatus::Disetujui->value])
            ->where('end_at', '>=', now())
            ->with('room:id,name')
            ->orderBy('start_at')
            ->limit(5)
            ->get();
    }

    public function tugasSaya(): Collection
    {
        if (! $this->punyaTugasTeknisi()) {
            return collect();
        }

        return WorkOrder::query()
            ->where('assigned_to', Auth::id())
            ->whereIn('status', [WorkOrderStatus::Ditugaskan->value, WorkOrderStatus::Dikerjakan->value])
            ->with('asset:id,code,name')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();
    }

    public function punyaTugasTeknisi(): bool
    {
        return (bool) Auth::user()?->hasRole('teknisi');
    }

    /** Pintasan sesuai izin pengguna */
    public function pintasan(): array
    {
        return array_values(array_filter([
            LoanResource::canCreate()
                ? ['Ajukan Peminjaman', LoanResource::getUrl('create'), 'heroicon-o-arrows-right-left']
                : null,
            RoomBookingResource::canCreate()
                ? ['Pesan Ruang', RoomBookingResource::getUrl('create'), 'heroicon-o-calendar-days']
                : null,
            DamageReportResource::canViewAny()
                ? ['Laporkan Kerusakan', DamageReportResource::getUrl('index'), 'heroicon-o-exclamation-triangle']
                : null,
            $this->punyaTugasTeknisi() && WorkOrderResource::canViewAny()
                ? ['Perintah Kerja', WorkOrderResource::getUrl('index'), 'heroicon-o-wrench-screwdriver']
                : null,
            ['Cek Aset', route('asset.check'), 'heroicon-o-qr-code'],
        ]));
    }
}