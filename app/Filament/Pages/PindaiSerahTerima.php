<?php

namespace App\Filament\Pages;

use App\Enums\AssetCondition;
use App\Enums\BorrowerType;
use App\Enums\LoanPurpose;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\Laboratory;
use App\Models\Loan;
use App\Models\Scopes\LoanVisibilityScope;
use App\Models\User;
use App\Services\ConsumableLoanService;
use App\Services\LoanService;
use App\Services\RoomBookingService;
use App\Services\ScanService;
use App\Support\Phone;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PindaiSerahTerima extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Laboratorium';

    protected static ?string $navigationLabel = 'Pindai Pinjam/Kembali';

    protected static ?string $title = 'Pindai Pinjam/Kembali';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.pindai-serah-terima';

    /* ------------------------ Keadaan: pindai umum ------------------------ */

    public string $kode = '';

    public ?string $pesan = null;

    public bool $berhasil = false;

    public string $aksi = '';

    /* --------------------- Keadaan: pinjaman berjalan --------------------- */

    public ?int $loanId = null;

    public array $kondisi = [];

    public array $catatan = [];

    /** Pengembalian aset kolektif: [itemId => ['returned' => n, 'damaged' => n]] */
    public array $kolektifKembali = [];

    /** Hasil pindai aset kolektif yang menunggu pilihan petugas */
    public array $kolektif = [];

    /* --------------------- Keadaan: peminjaman baru ----------------------- */

    /**
     * [assetId => ['code', 'name', 'lab', 'loanable_by', 'collective', 'max', 'quantity']]
     * Aset kolektif memakai 'quantity'; aset tunggal selalu 1.
     */
    public array $keranjang = [];

    public ?int $userId = null;

    public string $cariPeminjam = '';

    public string $cariMassal = '';

    public ?int $labId = null;

    public string $purposeType = 'praktikum';

    public ?string $courseName = null;

    public ?int $supervisorId = null;

    public ?string $startAt = null;

    public ?string $endAt = null;

    public ?string $uraian = null;

    /** Mulai peminjaman yang isinya hanya alat massal */
    public function mulaiMassal(): void
    {
        $this->aksi = 'baru';
        $this->berhasil = true;
        $this->pesan = null;
        $this->loanId = null;
        $this->kolektif = [];

        // Kotak pindai tetap aktif: alat ber-QR masih bisa ditambahkan
        $this->dispatch('fokus-kode');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['laboran', 'kabag_sarpras', 'super_admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->setelUlangJadwal();
    }

    /* ------------------------------- Pindai -------------------------------- */

    public function pindai(): void
    {
        $this->pesan = null;

        if (blank($this->kode)) {
            return;
        }

        $hasil = ScanService::scan($this->kode);
        $this->kode = '';

        if (! $hasil['ok']) {
            $this->berhasil = false;
            $this->pesan = $hasil['pesan'];

            return;
        }

        // Alat bebas: masukkan ke keranjang peminjaman baru
        if ($hasil['aksi'] === 'baru') {
            $this->kolektif = [];
            $this->tambahKeKeranjang($hasil['asset']);

            return;
        }

        // Aset kolektif: petugas memilih pinjam baru atau proses yang berjalan
        if ($hasil['aksi'] === 'kolektif') {
            $this->tampilkanPilihanKolektif($hasil['asset'], $hasil['bisa_pinjam'], $hasil['loans']);

            return;
        }

        // Alat sedang dalam peminjaman: keranjang dikosongkan agar tidak tercampur
        $this->bukaPinjaman($hasil['loan'], $hasil['aksi']);
    }

    /** Siapkan tampilan serahkan atau terima kembali untuk satu peminjaman */
    protected function bukaPinjaman(Loan $loan, string $aksi): void
    {
        $this->keranjang = [];
        $this->kolektif = [];
        $this->berhasil = true;
        $this->pesan = null;
        $this->aksi = $aksi;
        $this->loanId = $loan->getKey();

        if ($this->aksi !== 'kembalikan') {
            return;
        }

        $this->kondisi = [];
        $this->catatan = [];
        $this->kolektifKembali = [];
        $this->massalKembali = [];

        foreach ($loan->items()->with('asset')->get() as $item) {
            $this->catatan[$item->getKey()] = null;

            if ($item->isCollective()) {
                $this->kolektifKembali[$item->getKey()] = [
                    'returned' => $item->quantity,
                    'damaged' => 0,
                ];

                continue;
            }

            $this->kondisi[$item->getKey()] = ($item->condition_before ?? AssetCondition::Baik)->value;
        }

        foreach ($loan->consumableItems as $item) {
            $this->massalKembali[$item->getKey()] = [
                'returned' => $item->quantity,
                'damaged' => 0,
                'note' => null,
            ];
        }
    }

    /* ---------------------------- Aset kolektif ---------------------------- */

    protected function tampilkanPilihanKolektif(Asset $asset, bool $bisaPinjam, $loans): void
    {
        $this->pesan = null;
        $this->berhasil = true;

        $this->kolektif = [
            'asset_id' => $asset->getKey(),
            'code' => $asset->code,
            'name' => $asset->name,
            'quantity' => (int) $asset->quantity,
            'bisa_pinjam' => $bisaPinjam,
            'sudah_di_keranjang' => isset($this->keranjang[$asset->getKey()]),
            'loans' => $loans->map(fn (Loan $loan) => [
                'id' => $loan->getKey(),
                'number' => $loan->loan_number,
                'borrower' => $loan->borrower_name,
                'status' => $loan->status->getLabel(),
                'status_color' => $loan->status->getColor(),
                'aksi' => $loan->status === LoanStatus::Disetujui ? 'serahkan' : 'kembalikan',
                'jumlah' => (int) $loan->items->sum('quantity'),
                'jadwal' => $loan->start_at->translatedFormat('d M H:i')
                    .' – '.$loan->end_at->translatedFormat('d M H:i'),
            ])->all(),
        ];
    }

    /** Masukkan aset kolektif hasil pindai ke peminjaman baru */
    public function pinjamKolektif(): void
    {
        $asset = isset($this->kolektif['asset_id'])
            ? Asset::allUnits()->with('room.laboratory')->find($this->kolektif['asset_id'])
            : null;

        $this->kolektif = [];

        if ($asset) {
            $this->tambahKeKeranjang($asset);
        }
    }

    /** Proses salah satu peminjaman berjalan yang memuat aset kolektif */
    public function prosesPinjaman(int $loanId): void
    {
        $loan = Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->find($loanId);

        if (! $loan || ! ScanService::canHandle($loan) || ! in_array($loan->status, [
            LoanStatus::Disetujui, LoanStatus::Dipinjam, LoanStatus::Terlambat,
        ], true)) {
            $this->gagal('Peminjaman tidak dapat diproses.');

            return;
        }

        $this->bukaPinjaman($loan, $loan->status === LoanStatus::Disetujui ? 'serahkan' : 'kembalikan');
    }

    public function tutupPilihanKolektif(): void
    {
        $this->kolektif = [];
        $this->dispatch('fokus-kode');
    }

    protected function tambahKeKeranjang(Asset $asset): void
    {
        if (isset($this->keranjang[$asset->getKey()])) {
            $this->pesan = $asset->isCollective()
                ? $asset->code.' sudah ada di daftar. Ubah jumlahnya di daftar alat.'
                : $asset->code.' sudah ada di daftar.';
            $this->berhasil = false;

            return;
        }

        $lab = $asset->room?->laboratory;

        // Laboran hanya boleh memproses alat di lab yang diampunya
        $user = Auth::user();

        if ($user->hasRole('laboran') && ! $user->canSeeAllUnits()) {
            if (! $lab || ! in_array($lab->getKey(), $user->laboratoryIds(), true)) {
                $this->pesan = $asset->code.' berada di luar laboratorium yang Anda tangani.';
                $this->berhasil = false;

                return;
            }
        }

        // Alat pertama menentukan lab peminjaman
        if (empty($this->keranjang) && $lab) {
            $this->labId = $lab->getKey();
        }

        $this->keranjang[$asset->getKey()] = [
            'code' => $asset->code,
            'name' => $asset->name,
            'lab' => $lab?->name ?? 'Umum',
            'loanable_by' => $asset->loanable_by?->value ?? 'semua',
            'collective' => $asset->isCollective(),
            'max' => max(1, (int) $asset->quantity),
            'quantity' => 1,
        ];

        $this->loanId = null;
        $this->aksi = 'baru';
        $this->berhasil = true;
    }

    public function hapusDariKeranjang(int $assetId): void
    {
        unset($this->keranjang[$assetId]);

        // Tetap di mode peminjaman baru selama masih ada alat massal di keranjang
        if (empty($this->keranjang) && empty($this->barisMassal())) {
            $this->aksi = '';
        }
    }

    /* -------------------------- Pilih peminjam ---------------------------- */

    public function hasilCariPeminjam()
    {
        if (strlen(trim($this->cariPeminjam)) < 2) {
            return collect();
        }

        $kata = '%'.trim($this->cariPeminjam).'%';

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                'mahasiswa', 'dosen', 'kaprodi', 'laboran', 'staf_ruang', 'teknisi', 'kabag',
            ]))
            ->where(fn ($q) => $q
                ->where('name', 'like', $kata)
                ->orWhere('nim', 'like', $kata)
                ->orWhere('nip', 'like', $kata)
                ->orWhere('email', 'like', $kata))
            ->with('roles:id,name')
            ->orderBy('name')
            ->limit(15)
            ->get();
    }

    public function pilihPeminjam(int $id): void
    {
        $this->userId = $id;
        $this->cariPeminjam = '';
        $this->setelUlangJadwal();
    }

    public function peminjam(): ?User
    {
        return $this->userId ? User::with('roles:id,name')->find($this->userId) : null;
    }

    /* ------------------------------ Jadwal -------------------------------- */

    public function setelUlangJadwal(): void
    {
        $mulai = now()->startOfHour();

        $this->startAt ??= $mulai->format('Y-m-d\TH:i');

        $batas = LoanService::maxEnd(
            $this->startAt,
            BorrowerType::Internal,
            $this->labId,
            $this->peminjam(),
        );

        $usulan = Carbon::parse($this->startAt)->addHours(4);

        if ($batas && $usulan->gt($batas)) {
            $usulan = $batas;
        }

        $this->endAt = $usulan->format('Y-m-d\TH:i');
    }

    public function updatedLabId(): void
    {
        $this->setelUlangJadwal();
    }

    public function batasLabel(): string
    {
        return LoanService::maxDurationLabel(
            BorrowerType::Internal,
            $this->labId,
            $this->peminjam(),
        );
    }

    /* --------------------------- Buat peminjaman --------------------------- */

    public function buatPinjaman(): void
    {
        $this->pesan = null;

        $peminjam = $this->peminjam();

        if (! $peminjam) {
            $this->gagal('Pilih peminjam terlebih dahulu.');

            return;
        }

        if (empty($this->keranjang) && empty($this->barisMassal())) {
            $this->gagal('Belum ada alat yang dipilih.');

            return;
        }

        if (blank($this->startAt) || blank($this->endAt)) {
            $this->gagal('Jadwal belum lengkap.');

            return;
        }

        $mulai = Carbon::parse($this->startAt);
        $selesai = Carbon::parse($this->endAt);

        if ($selesai->lte($mulai)) {
            $this->gagal('Batas kembali harus setelah waktu mulai.');

            return;
        }

        $batas = LoanService::maxEnd($mulai, BorrowerType::Internal, $this->labId, $peminjam);

        if ($batas && $selesai->gt($batas)) {
            $this->gagal('Melewati batas maksimal. '.$this->batasLabel());

            return;
        }

        // Alat khusus dosen tidak boleh dipinjam mahasiswa
        if ($peminjam->isStudent()) {
            $terlarang = collect($this->keranjang)
                ->filter(fn ($a) => $a['loanable_by'] === 'dosen_saja')
                ->pluck('code');

            if ($terlarang->isNotEmpty()) {
                $this->gagal('Alat berikut hanya untuk dosen: '.$terlarang->implode(', '));

                return;
            }
        }

        // Cek ketersediaan per jumlah pada rentang waktu yang diminta
        $diminta = $this->jumlahDiminta();
        $terpakai = Loan::reservedQuantities($mulai, $selesai);
        $kurang = [];

        foreach ($diminta as $assetId => $jumlah) {
            $a = $this->keranjang[$assetId];
            $sisa = max(0, (int) ($a['max'] ?? 1) - ($terpakai[$assetId] ?? 0));

            if ($jumlah > $sisa) {
                $kurang[] = ($a['collective'] ?? false)
                    ? $a['code'].' (diminta '.$jumlah.', tersedia '.$sisa.')'
                    : $a['code'];
            }
        }

        if (! empty($kurang)) {
            $this->gagal('Tidak tersedia pada jam tersebut: '.implode(', ', $kurang));

            return;
        }

        $jenis = LoanPurpose::tryFrom($this->purposeType) ?? LoanPurpose::Lainnya;

        if ($jenis->requiresCourse() && blank($this->courseName)) {
            $this->gagal('Mata kuliah wajib diisi untuk keperluan '.$jenis->getLabel().'.');

            return;
        }

        if ($jenis->requiresSupervisor() && blank($this->supervisorId)) {
            $this->gagal('Dosen penanggung jawab wajib diisi untuk keperluan '.$jenis->getLabel().'.');

            return;
        }

        $massal = $this->barisMassal();

        // Periksa stok alat massal sebelum apa pun disimpan
        $errors = ConsumableLoanService::check($massal);

        if (! empty($errors)) {
            $this->gagal(implode(' ', $errors));

            return;
        }

        try {
            $loan = LoanService::createAndHandOut(
                [
                    'borrower_type' => BorrowerType::Internal,
                    'user_id' => $peminjam->getKey(),
                    'laboratory_id' => $this->labId,
                    'borrower_name' => $peminjam->name,
                    'borrower_identity' => $peminjam->identity_number,
                    'borrower_phone' => $peminjam->phone,
                    'borrower_email' => $peminjam->email,
                    'purpose' => $this->uraian ?: 'Peminjaman langsung di laboratorium.',
                    'purpose_type' => $jenis,
                    'course_name' => $this->courseName,
                    'supervisor_id' => $this->supervisorId,
                    'start_at' => $mulai,
                    'end_at' => $selesai,
                ],
                $this->jumlahDiminta(),
                'Peminjaman langsung lewat pindai QR',
                $massal,
            );
        } catch (\DomainException $e) {
            // Stok diambil orang lain pada detik yang sama; tidak ada yang tersimpan
            $this->gagal($e->getMessage());

            return;
        }

        Notification::make()
            ->success()
            ->title('Peminjaman dibuat')
            ->body($loan->loan_number.' — alat langsung diserahkan.')
            ->send();

        $this->selesai();
    }

    /* ---------------------- Serahkan & terima kembali ---------------------- */

    public function serahkan(): void
    {
        $loan = $this->loan();

        if (! $loan || $loan->status !== LoanStatus::Disetujui) {
            return;
        }

        // Pelengkap reservasi pihak luar: tunggu pelunasan sewa
        $reservasi = $loan->roomBooking;

        if ($reservasi && ($alasan = RoomBookingService::handOutBlockedReason($reservasi))) {
            $this->gagal('Pelengkap '.$reservasi->booking_number.' belum bisa diserahkan. '.$alasan);

            return;
        }

        $handover = LoanService::handOut($loan, 'Serah terima lewat pindai QR');

        Notification::make()
            ->success()
            ->title('Alat diserahkan')
            ->body('Berita acara '.$handover->number.' dibuat.')
            ->send();

        $this->selesai();
    }

    public function kembalikan(): void
    {
        $loan = $this->loan();

        if (! $loan || ! $loan->isOngoing()) {
            return;
        }

        $kondisi = [];

        foreach ($this->kondisi as $itemId => $nilai) {
            $kondisi[$itemId] = [
                'condition' => $nilai,
                'note' => $this->catatan[$itemId] ?? null,
            ];
        }

        foreach ($this->kolektifKembali as $itemId => $isian) {
            $kondisi[$itemId] = [
                'returned' => $isian['returned'] ?? null,
                'damaged' => $isian['damaged'] ?? 0,
                'note' => $this->catatan[$itemId] ?? null,
            ];
        }

        $handover = LoanService::handBack(
            $loan,
            $kondisi,
            'Pengembalian lewat pindai QR',
            $this->massalKembali,
        );

        Notification::make()
            ->success()
            ->title('Pengembalian dicatat')
            ->body('Berita acara '.$handover->number.' dibuat.')
            ->send();

        $this->selesai();
    }

    public function batal(): void
    {
        $this->selesai();
    }

    /* ----------------------------- Pembantu ------------------------------- */

    public function loan(): ?Loan
    {
        if (! $this->loanId) {
            return null;
        }

        return Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->with([
                'items.asset:id,code,name,quantity',
                'consumableItems.consumable:id,name,unit_of_measure',
                'laboratory:id,name',
            ])
            ->find($this->loanId);
    }

    public function kondisiOptions(): array
    {
        return collect(AssetCondition::cases())
            ->mapWithKeys(fn (AssetCondition $c) => [$c->value => $c->getLabel()])
            ->all();
    }

    public function keperluanOptions(): array
    {
        return collect(LoanPurpose::cases())
            ->mapWithKeys(fn (LoanPurpose $p) => [$p->value => $p->getLabel()])
            ->all();
    }

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

    public function dosenOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['dosen', 'kaprodi']))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function perluMataKuliah(): bool
    {
        return LoanPurpose::tryFrom($this->purposeType)?->requiresCourse() ?? false;
    }

    public function perluDosen(): bool
    {
        return LoanPurpose::tryFrom($this->purposeType)?->requiresSupervisor() ?? false;
    }

    public function teleponTampil(?string $n): string
    {
        return Phone::display($n);
    }

    protected function gagal(string $pesan): void
    {
        $this->berhasil = false;
        $this->pesan = $pesan;
    }

    protected function selesai(): void
    {
        $this->reset([
            'loanId', 'pesan', 'berhasil', 'aksi', 'kondisi', 'catatan', 'kode',
            'keranjang', 'userId', 'cariPeminjam', 'courseName', 'supervisorId', 'uraian', 'keranjangMassal', 'massalKembali',
            'kolektif', 'kolektifKembali',
        ]);

        $this->purposeType = 'praktikum';
        $this->startAt = null;
        $this->setelUlangJadwal();
        $this->dispatch('fokus-kode');
    }

    /** [consumableId => jumlah] */
    public array $keranjangMassal = [];

    /** Pengembalian: [itemId => ['returned' => n, 'damaged' => n, 'note' => ...]] */
    public array $massalKembali = [];

    public function alatMassalTersedia()
    {
        return ConsumableLoanService::available($this->labId);
    }

    /** Batas tampil awal alat massal sebelum mencari */
    public const MASSAL_AWAL = 5;

    /**
     * Alat massal yang ditampilkan: yang sudah diisi jumlahnya selalu tampil,
     * sisanya disaring kata kunci. Tanpa kata kunci hanya 5 teratas.
     *
     * @return array{daftar: Collection, total: int, cocok: int}
     */
    public function alatMassalTampil(): array
    {
        $semua = $this->alatMassalTersedia();
        $kata = mb_strtolower(trim($this->cariMassal));

        $dipilih = $semua->filter(fn ($m) => (int) ($this->keranjangMassal[$m->id] ?? 0) > 0);
        $lainnya = $semua->reject(fn ($m) => (int) ($this->keranjangMassal[$m->id] ?? 0) > 0);

        $cocok = $kata === ''
            ? $lainnya
            : $lainnya->filter(fn ($m) => str_contains(mb_strtolower($m->name.' '.$m->code), $kata));

        $batas = $kata === '' ? self::MASSAL_AWAL : 20;

        return [
            'daftar' => $dipilih->concat($cocok->take(max($batas - $dipilih->count(), 0)))->values(),
            'total' => $semua->count(),
            'cocok' => $cocok->count(),
        ];
    }

    public function tambahMassal(int $id): void
    {
        $tersedia = ConsumableLoanService::availableQuantity(Consumable::find($id));
        $sekarang = (int) ($this->keranjangMassal[$id] ?? 0);

        if ($sekarang >= $tersedia) {
            $this->gagal('Stok alat massal ini sudah habis dipilih.');

            return;
        }

        $this->keranjangMassal[$id] = $sekarang + 1;

        $this->pesan = null;
        $this->aksi = 'baru';
        $this->berhasil = true;
    }

    /**
     * Keranjang aset dalam bentuk [asset_id => jumlah].
     * Aset tunggal selalu 1; aset kolektif dibatasi 1 sampai jumlah unitnya.
     */
    protected function jumlahDiminta(): array
    {
        return collect($this->keranjang)
            ->map(fn (array $a) => ($a['collective'] ?? false)
                ? min(max(1, (int) ($a['quantity'] ?? 1)), (int) ($a['max'] ?? 1))
                : 1)
            ->all();
    }

    /** Keranjang alat massal dalam bentuk baris untuk layanan */
    protected function barisMassal(): array
    {
        return collect($this->keranjangMassal)
            ->map(fn ($jumlah, $id) => ['consumable_id' => (int) $id, 'quantity' => (int) $jumlah])
            ->filter(fn ($row) => $row['quantity'] > 0)
            ->values()
            ->all();
    }

    public function hapusMassal(int $id): void
    {
        unset($this->keranjangMassal[$id]);

        if (empty($this->keranjang) && empty($this->keranjangMassal)) {
            $this->aksi = '';
        }
    }
}
