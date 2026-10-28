<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\AssetCondition;
use App\Enums\BorrowerType;
use App\Enums\DamageSeverity;
use App\Enums\HandoverType;
use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\Approval;
use App\Models\Asset;
use App\Models\DamageReport;
use App\Models\Handover;
use App\Models\HandoverItem;
use App\Models\Laboratory;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Scopes\LoanVisibilityScope;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanService
{
    /**
     * Aset yang masih punya unit bebas pada rentang waktu,
     * opsional disaring per lab dan peminjam.
     *
     * Setiap aset diberi atribut sementara `available_quantity`:
     * aset tunggal selalu 1, aset kolektif berisi sisa unitnya.
     */
    public static function availableAssets(
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $exceptLoanId = null,
        ?int $laboratoryId = null,
        ?User $borrower = null,
        bool $outsideLaboratories = false,
    ): Collection {
        $terpakai = Loan::reservedQuantities($start, $end, $exceptLoanId);

        return Asset::query()
            ->allUnits()
            ->availableForLoan()
            ->when($laboratoryId, fn ($q) => $q->inLaboratory($laboratoryId))
            // Pelengkap reservasi: alat laboratorium tidak ikut dipinjamkan
            ->when($outsideLaboratories, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('room_id')
                ->orWhereHas('room', fn ($r) => $r->whereNull('laboratory_id'))))
            ->loanableFor($borrower)
            ->with([
                'unit:id,name',
                'room:id,name,building_id,laboratory_id',
                'category:id,name',
            ])
            ->orderBy('code')
            ->get()
            ->each(fn (Asset $a) => $a->setAttribute(
                'available_quantity',
                max(0, max(1, (int) $a->quantity) - ($terpakai[$a->getKey()] ?? 0)),
            ))
            ->filter(fn (Asset $a) => $a->available_quantity > 0)
            ->values();
    }

        /**
     * Yang boleh menyetujui atau menolak permohonan:
     * Kabag Sarpras dan Super Admin untuk semua, laboran untuk lab yang diampunya.
     */
    public static function canDecide(Loan $loan, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || $loan->status !== LoanStatus::Diajukan || $loan->isBookingEquipment()) {
            return false;
        }

        if ($user->hasAnyRole(['kabag_sarpras', 'super_admin'])) {
            return true;
        }

        return $user->hasRole('laboran')
            && $loan->laboratory_id
            && in_array((int) $loan->laboratory_id, array_map('intval', $user->laboratoryIds()), true);
    }

    /** Nama peran yang dicatat di riwayat persetujuan */
    protected static function deciderRole(): string
    {
        $user = Auth::user();

        foreach (['super_admin', 'kabag_sarpras', 'laboran'] as $peran) {
            if ($user?->hasRole($peran)) {
                return $peran;
            }
        }

        return 'kabag_sarpras';
    }

    /** Ka. Sarpras menyetujui — aset belum berpindah tangan */
    public static function approve(Loan $loan, ?string $note = null): void
    {
        DB::transaction(function () use ($loan, $note) {
            $loan->forceFill([
                'status' => LoanStatus::Disetujui,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ])->save();

            Approval::record($loan, ApprovalAction::Setuju, 1, static::deciderRole(), $note);
        });
        NotifyService::toUser(
            $loan->user_id,
            'Peminjaman disetujui',
            $loan->loan_number.' — silakan ambil alat di '
            .($loan->laboratory?->name ?? 'Bagian Sarana dan Prasarana').'.',
            LoanResource::getUrl('view', ['record' => $loan]),
            'success',
            'heroicon-o-check-circle',
        );
    }

    public static function reject(Loan $loan, string $reason): void
    {
        DB::transaction(function () use ($loan, $reason) {
            $loan->forceFill([
                'status' => LoanStatus::Ditolak,
                'rejection_reason' => $reason,
            ])->save();

            // Stok alat massal dilepas oleh LoanObserver saat status berubah
            Approval::record($loan, ApprovalAction::Tolak, 1, 'kabag_sarpras', $reason);
        });

        NotifyService::toUser(
            $loan->user_id,
            'Peminjaman ditolak',
            $loan->loan_number.' — '.Str::limit($reason, 120),
            LoanResource::getUrl('view', ['record' => $loan]),
            'danger',
            'heroicon-o-x-circle',
        );
    }

    /**
     * Serah terima keluar.
     * Observer Loan mengubah status aset tunggal jadi Dipinjam.
     */
    public static function handOut(Loan $loan, ?string $note = null): Handover
    {
        return DB::transaction(function () use ($loan, $note) {
            $loan->forceFill(['status' => LoanStatus::Dipinjam])->save();

            $handover = Handover::create([
                'type' => HandoverType::Peminjaman,
                'reference_type' => $loan->getMorphClass(),
                'reference_id' => $loan->getKey(),
                'from_user_id' => Auth::id(),
                'to_user_id' => $loan->user_id,
                'unit_id' => $loan->user?->unit_id,
                'handover_date' => now()->toDateString(),
                'notes' => $note,
            ]);

            foreach ($loan->items()->with('asset')->get() as $item) {
                HandoverItem::create([
                    'handover_id' => $handover->getKey(),
                    'asset_id' => $item->asset_id,
                    'quantity' => $item->quantity,
                    'condition' => $item->condition_before ?? $item->asset?->condition,
                ]);
            }

            return $handover;
        });
    }

    /**
     * Pengembalian.
     *
     * $conditions per loan_item_id:
     * - aset tunggal  : ['condition' => 'baik', 'note' => '...']
     * - aset kolektif : ['returned' => 47, 'damaged' => 3, 'note' => '...']
     *
     * $consumableRows: lihat ConsumableLoanService::handBack()
     */
    public static function handBack(
        Loan $loan,
        array $conditions = [],
        ?string $note = null,
        array $consumableRows = [],
    ): Handover {
        return DB::transaction(function () use ($loan, $conditions, $note, $consumableRows) {
            foreach ($loan->items()->with('asset')->get() as $item) {
                $row = $conditions[$item->getKey()] ?? [];
                $catatan = filled($row['note'] ?? null) ? trim($row['note']) : null;

                if ($item->isCollective()) {
                    [$kembali, $rusak] = static::collectiveReturn($item->quantity, $row);

                    $item->forceFill([
                        // Kondisi aset kolektif tidak diubah; kerusakan dicatat per jumlah
                        'condition_after' => $item->condition_before,
                        'returned_quantity' => $kembali,
                        'damaged_quantity' => $rusak,
                        'damage_note' => $catatan,
                        'returned_at' => now(),
                    ])->save();

                    continue;
                }

                $item->forceFill([
                    'condition_after' => $row['condition'] ?? $item->condition_before,
                    'damage_note' => $catatan,
                    'returned_at' => now(),
                ])->save();
            }

            // Observer melepaskan status aset dan menerapkan kondisi akhir
            $loan->forceFill([
                'status' => LoanStatus::Dikembalikan,
                'returned_at' => now(),
            ])->save();

            $handover = Handover::create([
                'type' => HandoverType::Pengembalian,
                'reference_type' => $loan->getMorphClass(),
                'reference_id' => $loan->getKey(),
                'from_user_id' => $loan->user_id,
                'to_user_id' => Auth::id(),
                'unit_id' => $loan->user?->unit_id,
                'handover_date' => now()->toDateString(),
                'notes' => $note,
            ]);

            foreach ($loan->items()->with('asset')->get() as $item) {
                HandoverItem::create([
                    'handover_id' => $handover->getKey(),
                    'asset_id' => $item->asset_id,
                    // Aset kolektif: yang benar-benar kembali, utuh maupun rusak
                    'quantity' => $item->isCollective()
                        ? (int) $item->returned_quantity + (int) $item->damaged_quantity
                        : 1,
                    'condition' => $item->condition_after,
                    'note' => $item->isCollective() ? $item->return_summary : null,
                ]);
            }

            // Alat massal, bila ada
            if ($loan->consumableItems()->exists()) {
                ConsumableLoanService::handBack($loan, $consumableRows);
            }

            // Aset yang kembali rusak atau kurang otomatis dilaporkan
            static::reportDamagedReturns($loan);

            return $handover;
        });
    }

    /**
     * Rapikan isian pengembalian aset kolektif.
     * Yang rusak dikurangkan dari yang kembali; selebihnya dianggap hilang.
     *
     * @return array{0: int, 1: int}  [kembali baik, rusak]
     */
    protected static function collectiveReturn(int $jumlah, array $row): array
    {
        $rusak = min(max((int) ($row['damaged'] ?? 0), 0), $jumlah);

        $kembali = isset($row['returned']) && $row['returned'] !== ''
            ? (int) $row['returned']
            : $jumlah - $rusak;

        $kembali = min(max($kembali, 0), $jumlah - $rusak);

        return [$kembali, $rusak];
    }

        /**
     * Buat laporan kerusakan untuk barang yang kembali rusak atau kurang.
     *
     * Aset tunggal dilaporkan bila kondisinya memburuk, atau bila kembali
     * dalam keadaan rusak dan petugas menulis catatan kerusakan.
     * Aset kolektif dilaporkan bila ada unit rusak atau hilang.
     */
    protected static function reportDamagedReturns(Loan $loan): void
    {
        foreach ($loan->items()->with('asset')->get() as $item) {
            $rusakDenganCatatan = ! $item->isCollective()
                && $item->condition_after
                && $item->condition_after !== AssetCondition::Baik
                && filled($item->damage_note);

            if (! $item->is_damaged && ! $rusakDenganCatatan) {
                continue;
            }

            if ($item->isCollective()) {
                $rincian = collect([
                    $item->damaged_quantity ? $item->damaged_quantity.' unit rusak' : null,
                    $item->lost_quantity ? $item->lost_quantity.' unit hilang' : null,
                ])->filter()->implode(', ');

                DamageReport::create([
                    'asset_id' => $item->asset_id,
                    'quantity' => (int) $item->damaged_quantity + (int) $item->lost_quantity,
                    'loan_id' => $loan->getKey(),
                    'reported_by' => Auth::id(),
                    'reporter_name' => Auth::user()?->name,
                    'description' => 'Ditemukan saat pengembalian pinjaman '.$loan->loan_number
                        .': '.$rincian.' dari '.$item->quantity.' unit dipinjam.'
                        .($item->damage_note ? ' '.$item->damage_note : ''),
                    'severity' => DamageSeverity::Sedang,
                ]);

                continue;
            }

            DamageReport::create([
                'asset_id' => $item->asset_id,
                'loan_id' => $loan->getKey(),
                'reported_by' => Auth::id(),
                'reporter_name' => Auth::user()?->name,
                'description' => 'Kerusakan ditemukan saat pengembalian pinjaman '
                    .$loan->loan_number.' ('.$item->condition_after?->getLabel().'). '.($item->damage_note ?? ''),
                'severity' => $item->condition_after === AssetCondition::RusakBerat
                    ? DamageSeverity::Berat
                    : DamageSeverity::Sedang,
            ]);
        }
    }

    public static function markOverdue(): int
    {
        // Dipanggil scheduler tanpa login, tapi tetap lepas scope untuk berjaga
        $loans = Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->overdue()
            ->get();

        foreach ($loans as $loan) {
            $loan->forceFill(['status' => LoanStatus::Terlambat])->save();

            NotifyService::toUser(
                $loan->user_id,
                'Peminjaman terlambat',
                $loan->loan_number.' — jatuh tempo '.$loan->end_at->format('d/m/Y H:i'),
                LoanResource::getUrl('view', ['record' => $loan]),
                'danger',
                'heroicon-o-clock',
            );
        }

        if ($loans->isNotEmpty()) {
            $loans->groupBy('laboratory_id')->each(function ($kelompok, $labId) {
                NotifyService::toLabStaff(
                    $labId ?: null,
                    $kelompok->count().' peminjaman terlambat',
                    'Perlu ditagih pengembaliannya.',
                    LoanResource::getUrl('index'),
                    'danger',
                    'heroicon-o-clock',
                );
            });
        }

        return $loans->count();
    }

    /** Batas hari maksimum berdasarkan jenis peminjam */
    public static function maxDays(BorrowerType|string|null $borrowerType): int
    {
        $value = $borrowerType instanceof BorrowerType
            ? $borrowerType->value
            : (string) ($borrowerType ?? 'internal');

        return $value === BorrowerType::Eksternal->value
            ? (int) config('sapra.loan.max_days_external', 7)
            : (int) config('sapra.loan.max_days_internal', 14);
    }

    /**
     * Batas akhir peminjaman.
     * Di lab: jam, sesuai aturan lab dan status peminjam.
     * Di luar lab: hari, sesuai config sapra.loan.
     */
    public static function maxEnd(
        CarbonInterface|string|null $start,
        BorrowerType|string|null $borrowerType = null,
        ?int $laboratoryId = null,
        ?User $borrower = null,
    ): ?Carbon {
        if (blank($start)) {
            return null;
        }

        $start = Carbon::parse($start);

        if ($laboratoryId && ($lab = Laboratory::find($laboratoryId))) {
            $jam = $borrower?->isStudent() ? $lab->student_max_hours : $lab->lecturer_max_hours;

            return $start->copy()->addHours($jam);
        }

        return $start->copy()->addDays(static::maxDays($borrowerType));
    }

    public static function maxDurationLabel(
        BorrowerType|string|null $borrowerType = null,
        ?int $laboratoryId = null,
        ?User $borrower = null,
    ): string {
        if ($laboratoryId && ($lab = Laboratory::find($laboratoryId))) {
            $jam = $borrower?->isStudent() ? $lab->student_max_hours : $lab->lecturer_max_hours;

            return $jam >= 24 && $jam % 24 === 0
                ? 'Maksimal '.($jam / 24).' hari.'
                : 'Maksimal '.$jam.' jam.';
        }

        return 'Maksimal '.static::maxDays($borrowerType).' hari.';
    }

    /**
     * Buat peminjaman yang langsung diserahkan, untuk pencatatan di loket lab.
     * Status langsung Dipinjam dan berita acara BAPP terbit.
     *
     * Aset dan alat massal dicatat dalam satu transaksi. Bila alat massal
     * tidak mencukupi, seluruhnya dibatalkan dan DomainException dilempar
     * dengan pesan yang siap ditampilkan.
     *
     * @param  array<int, int>  $assets  [asset_id => jumlah]; aset tunggal selalu 1
     * @param  array  $consumableRows  [['consumable_id' => 1, 'quantity' => 12], ...]
     */
    public static function createAndHandOut(
        array $data,
        array $assets,
        ?string $note = null,
        array $consumableRows = [],
    ): Loan {
        $loan = DB::transaction(function () use ($data, $assets, $consumableRows) {
            $loan = Loan::create($data + [
                'status' => LoanStatus::Disetujui,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            static::syncItems($loan, $assets);

            if (! empty($consumableRows)) {
                $errors = ConsumableLoanService::sync($loan, $consumableRows);

                if (! empty($errors)) {
                    // Batalkan seluruh transaksi, termasuk data peminjamannya
                    throw new \DomainException(implode(' ', $errors));
                }
            }

            return $loan->fresh(['items']);
        });

        // Di luar transaksi: handOut menerbitkan berita acara dan mengubah status aset
        static::handOut($loan, $note);

        return $loan->fresh();
    }

    /**
     * Ganti seluruh baris aset sebuah peminjaman.
     * Jumlah aset tunggal selalu 1; aset kolektif dibatasi jumlah unitnya.
     *
     * @param  array<int, int>  $assets  [asset_id => jumlah]
     */
    public static function syncItems(Loan $loan, array $assets): void
    {
        DB::transaction(function () use ($loan, $assets) {
            LoanItem::where('loan_id', $loan->getKey())->delete();

            $daftar = Asset::query()
                ->allUnits()
                ->whereIn('id', array_keys($assets))
                ->get();

            foreach ($daftar as $asset) {
                $jumlah = $asset->isCollective()
                    ? min(max(1, (int) $assets[$asset->getKey()]), (int) $asset->quantity)
                    : 1;

                LoanItem::create([
                    'loan_id' => $loan->getKey(),
                    'asset_id' => $asset->getKey(),
                    'quantity' => $jumlah,
                    'condition_before' => $asset->condition,
                ]);
            }
        });
    }

    /**
     * Alat tersedia, dikelompokkan menurut kategori, merek, model, dan ruang.
     * Alat dengan merek atau spesifikasi berbeda otomatis jadi kelompok terpisah.
     *
     * 'available' dihitung dalam unit: aset kolektif menyumbang sisa unitnya,
     * aset tunggal menyumbang 1.
     */
    public static function availableGroups(
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $exceptLoanId = null,
        ?int $laboratoryId = null,
        ?User $borrower = null,
        bool $outsideLaboratories = false,
    ): Collection {
        return static::availableAssets($start, $end, $exceptLoanId, $laboratoryId, $borrower, $outsideLaboratories)
            ->groupBy(fn (Asset $a) => static::groupKey($a))
            ->map(function (Collection $alat, string $key) {
                /** @var Asset $contoh */
                $contoh = $alat->first();

                // Unit terbaik lebih dulu: kondisi baik, lalu kode terkecil
                $urut = $alat->sortBy([
                    fn (Asset $a) => $a->condition === AssetCondition::Baik ? 0 : 1,
                    fn (Asset $a) => $a->code,
                ]);

                return [
                    'key' => $key,
                    'label' => static::groupLabel($contoh),
                    'available' => (int) $alat->sum('available_quantity'),
                    'collective' => $contoh->isCollective(),
                    // [asset_id => sisa unit], urutan menentukan unit yang dipakai
                    'stock' => $urut
                        ->mapWithKeys(fn (Asset $a) => [$a->getKey() => (int) $a->available_quantity])
                        ->all(),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    /**
     * Ubah pilihan kelompok dan jumlah menjadi daftar aset konkret beserta jumlahnya.
     *
     * @param  array  $rows  [['group_key' => ..., 'quantity' => 3], ...]
     * @return array{items: array<int, int>, ids: array<int>, errors: array<string>}
     *         items: [asset_id => jumlah]; ids dipertahankan untuk pemanggil lama
     */
    public static function resolveGroupSelection(
        array $rows,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $exceptLoanId = null,
        ?int $laboratoryId = null,
        ?User $borrower = null,
        bool $outsideLaboratories = false,
    ): array {
        $groups = static::availableGroups($start, $end, $exceptLoanId, $laboratoryId, $borrower, $outsideLaboratories)
            ->keyBy('key');

        $items = [];
        $errors = [];
        $diminta = [];

        foreach ($rows as $row) {
            $key = $row['group_key'] ?? null;
            $jumlah = (int) ($row['quantity'] ?? 0);

            if (blank($key) || $jumlah < 1) {
                continue;
            }

            // Baris ganda untuk kelompok sama: jumlahkan
            $diminta[$key] = ($diminta[$key] ?? 0) + $jumlah;
        }

        foreach ($diminta as $key => $jumlah) {
            $group = $groups->get($key);

            if (! $group) {
                $errors[] = 'Kelompok alat tidak lagi tersedia pada jadwal tersebut.';

                continue;
            }

            if ($jumlah > $group['available']) {
                $errors[] = $group['label'].' — diminta '.$jumlah
                    .' unit, tersedia '.$group['available'].'.';

                continue;
            }

            // Ambil dari aset teratas sampai jumlah terpenuhi
            $sisa = $jumlah;

            foreach ($group['stock'] as $assetId => $tersedia) {
                if ($sisa < 1) {
                    break;
                }

                $ambil = min($sisa, $tersedia);
                $items[$assetId] = ($items[$assetId] ?? 0) + $ambil;
                $sisa -= $ambil;
            }
        }

        return [
            'items' => $items,
            'ids' => array_keys($items),
            'errors' => $errors,
        ];
    }

    /** Kunci kelompok: kategori, merek, model, ruang */
    public static function groupKey(Asset $asset): string
    {
        return sha1(implode('|', [
            $asset->asset_category_id,
            mb_strtolower(trim($asset->brand ?? '')),
            mb_strtolower(trim($asset->model ?? '')),
            $asset->room_id,
        ]));
    }

    /** Label kelompok yang terbaca manusia */
    public static function groupLabel(Asset $asset): string
    {
        $nama = collect([$asset->name, $asset->brand, $asset->model])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->implode(' ');

        $lokasi = $asset->room?->name ?? $asset->unit?->name ?? 'Tanpa lokasi';

        return $nama.' · '.$lokasi;
    }
}
