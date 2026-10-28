<?php

namespace App\Filament\Pages;

use App\Enums\LoanStatus;
use App\Models\DamageReport;
use App\Models\Laboratory;
use App\Models\Loan;
use App\Services\ReminderService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BerandaLaboran extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Laboratorium';

    protected static ?string $navigationLabel = 'Beranda Lab';

    protected static ?string $title = 'Beranda Laboratorium';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.beranda-laboran';

    /** Hanya tampil bagi yang mengurus lab */
    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['laboran', 'kabag_sarpras', 'super_admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Lab yang menjadi tanggung jawab pengguna */
    public function laboratories()
    {
        $user = Auth::user();

        if ($user->hasRole('laboran')) {
            return $user->laboratories()->where('is_active', true)->get();
        }

        return Laboratory::active()->orderBy('name')->get();
    }

    public function stats(): array
    {
        return [
            'menunggu' => $this->query()->where('status', LoanStatus::Diajukan)->count(),
            'siap_ambil' => $this->query()->where('status', LoanStatus::Disetujui)->count(),
            'dipinjam' => $this->query()->ongoing()->count(),
            'hari_ini' => $this->query()
                ->where('status', LoanStatus::Dipinjam)
                ->whereDate('end_at', today())
                ->count(),
            'terlambat' => $this->overdueQuery()->count(),
            'kerusakan' => $this->damageQuery()->where('is_processed', false)->count(),
        ];
    }

    public function menungguList()
    {
        return $this->query()
            ->where('status', LoanStatus::Diajukan)
            ->with(['laboratory:id,name', 'items.asset:id,code,name'])
            ->orderBy('start_at')
            ->limit(10)
            ->get();
    }

    public function siapAmbilList()
    {
        return $this->query()
            ->where('status', LoanStatus::Disetujui)
            ->with(['laboratory:id,name', 'items.asset:id,code,name'])
            ->orderBy('start_at')
            ->limit(10)
            ->get();
    }

    public function terlambatList()
    {
        return $this->overdueQuery()
            ->with(['laboratory:id,name', 'items.asset:id,code,name'])
            ->withCount('reminders')
            ->orderBy('end_at')
            ->limit(15)
            ->get();
    }

    public function jatuhTempoList()
    {
        return $this->query()
            ->where('status', LoanStatus::Dipinjam)
            ->whereDate('end_at', today())
            ->where('end_at', '>=', now())
            ->with(['laboratory:id,name', 'items.asset:id,code,name'])
            ->orderBy('end_at')
            ->limit(10)
            ->get();
    }

    /* ------------------------------ Pembantu ------------------------------ */

    /** Scope visibilitas pada model Loan sudah membatasi per lab */
    protected function query(): Builder
    {
        return Loan::query();
    }

    protected function overdueQuery(): Builder
    {
        return $this->query()
            ->whereIn('status', [
                LoanStatus::Dipinjam->value,
                LoanStatus::Terlambat->value,
            ])
            ->where('end_at', '<', now());
    }

    protected function damageQuery(): Builder
    {
        return DamageReport::query();
    }

        /** Dipanggil saat tombol WA di beranda diklik */
        public function catatPengingat(int $loanId): void
        {
            $loan = Loan::find($loanId);
    
            if (! $loan) {
                return;
            }
    
            ReminderService::log($loan, ReminderService::compose($loan));
    
            Notification::make()
                ->success()
                ->title('Pengingat dicatat')
                ->send();
        }
}