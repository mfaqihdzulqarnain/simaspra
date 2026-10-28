<?php

namespace App\Filament\Pages;

use App\Enums\LoanPurpose;
use App\Http\Controllers\RekapLabController;
use App\Models\Laboratory;
use App\Services\LabStatsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RekapLaboratorium extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Laboratorium';

    protected static ?string $navigationLabel = 'Rekap Peminjaman';

    protected static ?string $title = 'Rekap Peminjaman Laboratorium';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.rekap-laboratorium';

    public ?int $labId = null;

    public string $periode = '6bulan';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole([
            'laboran', 'kabag_sarpras', 'super_admin', 'wadir', 'pimpinan',
        ]) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetak')
                ->label('Cetak Rekap')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('primary')
                ->visible(fn () => Auth::user()?->hasAnyRole(['laboran', 'kabag_sarpras', 'super_admin']))
                ->modalHeading('Cetak Rekap Peminjaman Alat')
                ->modalDescription('Satu laboratorium per cetakan, dalam bulan kalender penuh.')
                ->modalSubmitActionLabel('Cetak')
                ->fillForm(fn () => [
                    'lab' => $this->labId ?? array_key_first($this->labOptions()),
                    'periode' => 1,
                    'bulan' => now()->format('Y-m'),
                ])
                ->schema([
                    Select::make('lab')
                        ->label('Laboratorium')
                        ->options(fn () => $this->labOptions())
                        ->required()
                        ->native(false),

                    Select::make('periode')
                        ->label('Periode')
                        ->options(RekapLabController::PERIODE)
                        ->required()
                        ->native(false),

                    Select::make('bulan')
                        ->label('Sampai Bulan')
                        ->options(fn () => collect(range(0, 23))
                            ->mapWithKeys(fn (int $i) => [
                                now()->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m') => now()->startOfMonth()
                                    ->subMonthsNoOverflow($i)->locale('id')->translatedFormat('F Y'),
                            ])
                            ->all())
                        ->helperText('Contoh: 3 bulan sampai September 2026 berarti Juli–September 2026.')
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data) {
                    $url = route('rekap.lab.print', [
                        'lab' => $data['lab'],
                        'periode' => $data['periode'],
                        'bulan' => $data['bulan'],
                    ]);

                    // Buka PDF di tab baru
                    $this->js('window.open('.json_encode($url).', "_blank")');
                }),
        ];
    }

    public function mount(): void
    {
        $labs = $this->labOptions();

        // Laboran yang memegang satu lab langsung terpilih
        if (count($labs) === 1) {
            $this->labId = (int) array_key_first($labs);
        }
    }

    /* ----------------------------- Pilihan -------------------------------- */

    public function labOptions(): array
    {
        $user = Auth::user();

        if ($user->hasRole('laboran') && ! $user->canSeeAllUnits()) {
            return $user->laboratories()
                ->where('is_active', true)
                ->pluck('name', 'laboratories.id')
                ->all();
        }

        return Laboratory::active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function periodeOptions(): array
    {
        return [
            'bulan' => 'Bulan ini',
            '3bulan' => '3 bulan terakhir',
            '6bulan' => '6 bulan terakhir',
            'tahun' => 'Tahun ini',
            'semua' => 'Sejak awal',
        ];
    }

    public function dari(): Carbon
    {
        return match ($this->periode) {
            'bulan' => now()->startOfMonth(),
            '3bulan' => now()->subMonths(3)->startOfDay(),
            'tahun' => now()->startOfYear(),
            'semua' => Carbon::create(2000, 1, 1),
            default => now()->subMonths(6)->startOfDay(),
        };
    }

    public function sampai(): Carbon
    {
        return now()->endOfDay();
    }

    /* ------------------------------- Data --------------------------------- */

    public function summary(): array
    {
        return LabStatsService::summary($this->labId, $this->dari(), $this->sampai());
    }

    public function topAssets()
    {
        return LabStatsService::topAssets($this->labId, $this->dari(), $this->sampai());
    }

    public function lateBorrowers()
    {
        return LabStatsService::lateBorrowers($this->labId, $this->dari(), $this->sampai());
    }

    public function idleAssets()
    {
        return LabStatsService::idleAssets($this->labId, $this->dari(), $this->sampai());
    }

    public function monthlyTrend(): array
    {
        return LabStatsService::monthlyTrend($this->labId, $this->dari(), $this->sampai());
    }

    public function purposeBreakdown()
    {
        return LabStatsService::purposeBreakdown($this->labId, $this->dari(), $this->sampai());
    }

    public function purposeLabel(?string $nilai): string
    {
        return $nilai ? (LoanPurpose::tryFrom($nilai)?->getLabel() ?? $nilai) : '—';
    }

    public function labName(): string
    {
        return $this->labId
            ? (Laboratory::find($this->labId)?->name ?? 'Laboratorium')
            : 'Seluruh Laboratorium';
    }
}
