<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\BorrowerType;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Enums\LoanPurpose;
use App\Enums\LoanStatus;
use App\Enums\PaymentStatus;
use App\Models\Approval;
use App\Models\Loan;
use App\Models\NumberSequence;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Scopes\LoanVisibilityScope;
use App\Models\Scopes\RoomBookingVisibilityScope;
use App\Observers\LoanObserver;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reservasi Ruang.
 *
 * Aturan persetujuan:
 * - Kabag Sarpras dan Super Admin menyetujui semua reservasi.
 * - Penanggung jawab ruang menyetujui reservasi internal di ruangnya.
 * - Reservasi pihak luar selalu lewat Kabag Sarpras.
 */
class RoomBookingService
{
    /** Peran yang boleh menyetujui semua reservasi */
    public const APPROVER_ROLES = ['kabag_sarpras', 'super_admin'];

    /* ----------------------------- Jadwal --------------------------------- */

    /**
     * Reservasi lain yang bentrok.
     *
     * Setiap reservasi menempati ruang dari (mulai − persiapan) sampai
     * (selesai + beres-beres). Dua reservasi bentrok bila rentang tempatnya
     * beririsan, sehingga pembandingnya diperlebar sebesar persiapan + beres-beres.
     * Scope visibilitas dilepas: bentrok tetap bentrok meski milik orang lain.
     */
    public static function conflicts(
        int $roomId,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $exceptId = null,
    ): Collection {
        $room = Room::find($roomId);
        $jeda = ($room?->setupMinutes() ?? 0) + ($room?->cleanupMinutes() ?? 0);

        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        return RoomBooking::query()
            ->withoutGlobalScope(RoomBookingVisibilityScope::class)
            ->where('room_id', $roomId)
            ->occupying()
            ->overlapping($start->copy()->subMinutes($jeda), $end->copy()->addMinutes($jeda))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('start_at')
            ->get();
    }

    /**
     * Rentang ruang terpakai oleh satu reservasi, termasuk persiapan dan beres-beres.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function occupancy(?Room $room, CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        return [
            Carbon::parse($start)->subMinutes($room?->setupMinutes() ?? 0),
            Carbon::parse($end)->addMinutes($room?->cleanupMinutes() ?? 0),
        ];
    }

    /**
     * Pemakaian ruang yang menyentuh satu hari, termasuk hari persiapan
     * dan beres-beres, untuk membantu pemesan memilih jam.
     */
    public static function dayAgenda(int $roomId, CarbonInterface|string $date, ?int $exceptId = null): Collection
    {
        $hari = Carbon::parse($date);
        $room = Room::find($roomId);

        $awal = $hari->copy()->startOfDay()->subMinutes($room?->cleanupMinutes() ?? 0);
        $akhir = $hari->copy()->endOfDay()->addMinutes($room?->setupMinutes() ?? 0);

        return RoomBooking::query()
            ->withoutGlobalScope(RoomBookingVisibilityScope::class)
            ->where('room_id', $roomId)
            ->occupying()
            ->overlapping($awal, $akhir)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('start_at')
            ->get(['id', 'title', 'start_at', 'end_at', 'status']);
    }

    /**
     * Periksa isian reservasi.
     *
     * @return array<string, string>  [nama field => pesan]
     */
    public static function validate(array $data, ?int $exceptId = null, bool $baru = true): array
    {
        $errors = [];

        $room = isset($data['room_id']) ? Room::find($data['room_id']) : null;

        if (! $room || ! $room->is_active || ! $room->is_bookable) {
            return ['room_id' => 'Ruang ini tidak dibuka untuk reservasi.'];
        }

        if (! $room->allowsBookingType($data['booking_type'] ?? null)) {
            $errors['booking_type'] = 'Ruang ini tidak menerima jenis kegiatan tersebut.';
        }

        if (blank($data['start_at'] ?? null) || blank($data['end_at'] ?? null)) {
            $errors['end_at'] = 'Isi jam mulai dan jam selesai.';

            return $errors;
        }

        $mulai = Carbon::parse($data['start_at']);
        $selesai = Carbon::parse($data['end_at']);

        if ($selesai->lte($mulai)) {
            $errors['end_at'] = 'Jam selesai harus setelah jam mulai.';

            return $errors;
        }

        if ($baru && $mulai->lt(now()->subMinutes(30))) {
            $errors['start_at'] = 'Waktu mulai sudah lewat.';
        }

        $peserta = (int) ($data['attendees'] ?? 0);

        if ($room->capacity && $peserta > $room->capacity) {
            $errors['attendees'] = 'Melebihi kapasitas ruang ('.$room->capacity.' orang).';
        }

        $bentrok = static::conflicts($room->getKey(), $mulai, $selesai, $exceptId);

        if ($bentrok->isNotEmpty()) {
            $aturan = collect([
                $room->setupMinutes() ? 'persiapan '.Room::formatDuration($room->setupMinutes()).' sebelum acara' : null,
                $room->cleanupMinutes() ? 'beres-beres '.Room::formatDuration($room->cleanupMinutes()).' sesudah acara' : null,
            ])->filter()->implode(' dan ');

            $errors['start_at'] = 'Bentrok dengan: '.$bentrok
                ->map(fn (RoomBooking $b) => $b->start_at->format('d/m H.i').'–'.$b->end_at->format('d/m H.i')
                    .' '.Str::limit($b->title, 40))
                ->implode('; ')
                .($aturan ? '. Ruang ini memerlukan '.$aturan.'.' : '.');
        }

        return $errors;
    }

    /* --------------------------- Hak menyetujui ---------------------------- */

    public static function canApprove(RoomBooking $booking, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || $booking->status !== BookingStatus::Diajukan) {
            return false;
        }

        if ($user->hasAnyRole(static::APPROVER_ROLES)) {
            return true;
        }

        // Penanggung jawab ruang: hanya reservasi internal
        return ! $booking->isExternal()
            && $booking->room?->pic_user_id
            && (int) $booking->room->pic_user_id === (int) $user->getKey();
    }

    /** Pemesan membatalkan sendiri, atau penyetuju membatalkan reservasi yang sudah disetujui */
    public static function canCancel(RoomBooking $booking, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || ! $booking->status->occupiesRoom() || $booking->end_at?->isPast()) {
            return false;
        }

        // Pelengkap sudah di tangan pemesan: selesaikan pengembaliannya dulu
        if (static::equipmentLoan($booking)?->status->isOngoing()) {
            return false;
        }

        if ($user->hasAnyRole(static::APPROVER_ROLES)) {
            return true;
        }

        $milikSendiri = (int) $booking->user_id === (int) $user->getKey()
            || (int) $booking->created_by === (int) $user->getKey();

        $pjRuang = $booking->room?->pic_user_id
            && (int) $booking->room->pic_user_id === (int) $user->getKey();

        return $milikSendiri || $pjRuang;
    }

    /** Hanya reservasi yang masih menunggu, oleh pemesan atau penyetuju */
    public static function canEdit(RoomBooking $booking, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || $booking->status !== BookingStatus::Diajukan) {
            return false;
        }

        if ($user->hasAnyRole(static::APPROVER_ROLES)) {
            return true;
        }

        return (int) $booking->user_id === (int) $user->getKey()
            || (int) $booking->created_by === (int) $user->getKey();
    }

    /* ------------------------------ Transisi ------------------------------- */

    /**
     * Setujui. Bentrok diperiksa ulang karena bisa saja ada reservasi lain
     * yang disetujui lebih dulu sejak permohonan ini diajukan.
     *
     * @throws \DomainException bila bentrok
     */
    public static function approve(RoomBooking $booking, ?string $note = null, ?float $fee = null): void
    {
        DB::transaction(function () use ($booking, $note, $fee) {
            // Kunci ruang agar dua penyetuju tidak meloloskan jadwal yang sama
            Room::query()->whereKey($booking->room_id)->lockForUpdate()->first();

            $bentrok = static::conflicts($booking->room_id, $booking->start_at, $booking->end_at, $booking->getKey())
                ->where('status', BookingStatus::Disetujui);

            if ($bentrok->isNotEmpty()) {
                throw new \DomainException('Ruang sudah dipakai '.$bentrok
                    ->map(fn (RoomBooking $b) => $b->booking_number.' ('.$b->title.')')
                    ->implode(', ').' pada jam tersebut.');
            }

            $booking->forceFill([
                'status' => BookingStatus::Disetujui,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ])->save();

            // Biaya sewa pihak luar dikunci saat disetujui
            static::lockFee($booking, $fee);

            Approval::record($booking, ApprovalAction::Setuju, 1, static::approverRoleName(), $note);

            // Pelengkap ikut disetujui, tanpa notifikasi ganda ke pemesan
            $loan = static::equipmentLoan($booking);

            if ($loan && $loan->status === LoanStatus::Diajukan) {
                $loan->forceFill([
                    'status' => LoanStatus::Disetujui,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ])->save();

                Approval::record($loan, ApprovalAction::Setuju, 1, static::approverRoleName(), 'Disetujui bersama reservasi '.$booking->booking_number);
            }
        });

        NotifyService::toUser(
            $booking->user_id ?? $booking->created_by,
            'Reservasi ruang disetujui',
            $booking->booking_number.' — '.$booking->room?->name.', '.$booking->schedule_label
                .($booking->requiresPayment()
                    ? '. Biaya sewa '.RentalFeeService::rupiah($booking->fee_amount).', unggah bukti bayar di halaman reservasi.'
                    : ''),
            RoomBookingResource::getUrl('view', ['record' => $booking]),
            'success',
            'heroicon-o-check-circle',
        );
    }

    public static function reject(RoomBooking $booking, string $reason): void
    {
        DB::transaction(function () use ($booking, $reason) {
            $booking->forceFill([
                'status' => BookingStatus::Ditolak,
                'rejection_reason' => $reason,
            ])->save();

            Approval::record($booking, ApprovalAction::Tolak, 1, static::approverRoleName(), $reason);

            // Observer peminjaman melepas pelengkap
            static::equipmentLoan($booking)?->forceFill([
                'status' => LoanStatus::Ditolak,
                'rejection_reason' => 'Reservasi '.$booking->booking_number.' ditolak: '.$reason,
            ])->save();
        });

        NotifyService::toUser(
            $booking->user_id ?? $booking->created_by,
            'Reservasi ruang ditolak',
            $booking->booking_number.' — '.Str::limit($reason, 120),
            RoomBookingResource::getUrl('view', ['record' => $booking]),
            'danger',
            'heroicon-o-x-circle',
        );
    }

    public static function cancel(RoomBooking $booking, ?string $reason = null): void
    {
        $olehPemesan = in_array((int) Auth::id(), [(int) $booking->user_id, (int) $booking->created_by], true);
        $sudahDisetujui = $booking->status === BookingStatus::Disetujui;

        DB::transaction(function () use ($booking, $reason) {
            $booking->forceFill([
                'status' => BookingStatus::Batal,
                'cancellation_reason' => $reason,
            ])->save();

            $loan = static::equipmentLoan($booking);

            if ($loan && in_array($loan->status, [LoanStatus::Diajukan, LoanStatus::Disetujui], true)) {
                $loan->forceFill(['status' => LoanStatus::Batal])->save();
            }
        });

        $url = RoomBookingResource::getUrl('view', ['record' => $booking]);
        $isi = $booking->booking_number.' — '.$booking->room?->name.', '.$booking->schedule_label;

        if ($olehPemesan) {
            // Beri tahu penyetuju bila jadwal yang sudah disetujui dilepas
            if ($sudahDisetujui) {
                static::notifyApprovers($booking, 'Reservasi dibatalkan pemesan', $isi, 'gray');
            }

            return;
        }

        NotifyService::toUser(
            $booking->user_id ?? $booking->created_by,
            'Reservasi ruang dibatalkan',
            $isi.($reason ? ' — '.Str::limit($reason, 120) : ''),
            $url,
            'warning',
            'heroicon-o-x-circle',
        );
    }

    /** Dipanggil setelah reservasi dibuat */
    public static function notifyNew(RoomBooking $booking): void
    {
        static::notifyApprovers(
            $booking,
            'Reservasi ruang baru',
            $booking->booking_number.' — '.$booking->room?->name.', '.$booking->schedule_label
                .' · '.$booking->borrower_name,
            'warning',
        );
    }

    /* --------------------------- Jadwal publik ---------------------------- */

    /**
     * Jadwal ruang untuk halaman QR ruang: hari ini dan beberapa hari ke depan.
     *
     * Hanya berisi jam, nama kegiatan, dan status. Data pemesan tidak
     * pernah ikut, sesuai aturan data publik.
     *
     * @return array{sekarang: array, hari: array<int, array>}
     */
    public static function publicSchedule(Room $room, int $jumlahHari = 7): array
    {
        $awal = now()->startOfDay();
        $akhir = $awal->copy()->addDays($jumlahHari);

        $setup = $room->setupMinutes();
        $cleanup = $room->cleanupMinutes();

        $reservasi = RoomBooking::query()
            ->withoutGlobalScope(RoomBookingVisibilityScope::class)
            ->where('room_id', $room->getKey())
            ->occupying()
            ->overlapping($awal->copy()->subMinutes($cleanup), $akhir->copy()->addMinutes($setup))
            ->orderBy('start_at')
            ->get(['id', 'title', 'start_at', 'end_at', 'status']);

        // Pecah tiap reservasi menjadi blok persiapan, acara, dan beres-beres
        $blok = collect();

        foreach ($reservasi as $b) {
            $bagian = [
                'persiapan' => [$b->start_at->copy()->subMinutes($setup), $b->start_at->copy()],
                'acara' => [$b->start_at->copy(), $b->end_at->copy()],
                'beres' => [$b->end_at->copy(), $b->end_at->copy()->addMinutes($cleanup)],
            ];

            foreach ($bagian as $jenis => [$dari, $sampai]) {
                if ($dari->lt($sampai)) {
                    $blok->push([
                        'jenis' => $jenis,
                        'dari' => $dari,
                        'sampai' => $sampai,
                        'judul' => $b->title,
                        'menunggu' => $b->status === BookingStatus::Diajukan,
                    ]);
                }
            }
        }

        $blok = $blok->sortBy(fn ($x) => $x['dari']->timestamp)->values();

        // Keadaan saat ini
        $kini = now();
        $berjalan = $blok->first(fn ($x) => $x['dari']->lte($kini) && $x['sampai']->gt($kini));
        $berikut = $blok->first(fn ($x) => $x['jenis'] === 'acara' && $x['dari']->gt($kini));

        $sekarang = match (true) {
            $berjalan && $berjalan['jenis'] === 'acara' => [
                'kode' => 'dipakai',
                'judul' => 'Sedang dipakai',
                'isi' => $berjalan['judul'].' hingga '.static::jamLabel($berjalan['sampai'], $kini),
            ],
            (bool) $berjalan => [
                'kode' => 'persiapan',
                'judul' => $berjalan['jenis'] === 'persiapan' ? 'Sedang disiapkan' : 'Sedang dibereskan',
                'isi' => ($berjalan['jenis'] === 'persiapan' ? 'Untuk ' : 'Setelah ').$berjalan['judul']
                    .', hingga '.static::jamLabel($berjalan['sampai'], $kini),
            ],
            default => [
                'kode' => 'kosong',
                'judul' => 'Kosong',
                'isi' => $berikut
                    ? 'Kegiatan berikutnya: '.$berikut['judul'].', '.static::jamLabel($berikut['dari'], $kini)
                    : 'Belum ada kegiatan terjadwal dalam '.$jumlahHari.' hari ke depan.',
            ],
        };

        // Kelompokkan per hari, blok lintas hari dipotong
        $hari = [];

        for ($i = 0; $i < $jumlahHari; $i++) {
            $mulaiHari = $awal->copy()->addDays($i);
            $akhirHari = $mulaiHari->copy()->endOfDay();

            $isi = $blok
                ->filter(fn ($x) => $x['dari']->lt($akhirHari) && $x['sampai']->gt($mulaiHari))
                ->map(fn ($x) => [
                    'jenis' => $x['jenis'],
                    'judul' => $x['judul'],
                    'menunggu' => $x['menunggu'],
                    'jam' => ($x['dari']->lt($mulaiHari) ? '00.00' : $x['dari']->format('H.i'))
                        .'–'.($x['sampai']->gt($akhirHari) ? '24.00' : $x['sampai']->format('H.i')),
                ])
                ->values()
                ->all();

            $hari[] = [
                'tanggal' => $mulaiHari,
                'label' => match ($i) {
                    0 => 'Hari ini',
                    1 => 'Besok',
                    default => $mulaiHari->locale('id')->translatedFormat('l'),
                },
                'isi' => $isi,
            ];
        }

        return ['sekarang' => $sekarang, 'hari' => $hari];
    }

    /** "12.00" untuk hari ini, "besok 08.00", atau "Sen 6 Okt 08.00" */
    protected static function jamLabel(Carbon $waktu, Carbon $kini): string
    {
        return match (true) {
            $waktu->isSameDay($kini) => $waktu->format('H.i'),
            $waktu->isSameDay($kini->copy()->addDay()) => 'besok '.$waktu->format('H.i'),
            default => $waktu->locale('id')->translatedFormat('D j M').' '.$waktu->format('H.i'),
        };
    }

    /* ---------------------------- Biaya sewa ------------------------------ */

    /**
     * Perbarui perkiraan biaya sewa (reservasi yang belum disetujui).
     * Reservasi internal tidak dikenai biaya.
     */
    public static function refreshFee(RoomBooking $booking): void
    {
        if (! $booking->isExternal()) {
            $booking->forceFill([
                'fee_amount' => null,
                'rental_decree_id' => null,
                'fee_breakdown' => null,
                'payment_status' => null,
            ])->saveQuietly();

            return;
        }

        $hasil = RentalFeeService::calculate($booking->room, $booking->start_at, $booking->end_at);

        $booking->forceFill([
            'fee_amount' => $hasil['total'],
            'rental_decree_id' => $hasil['decree']?->getKey(),
            'fee_breakdown' => static::breakdownFrom($hasil),
        ])->saveQuietly();
    }

    /**
     * Kunci biaya saat disetujui. Penyetuju boleh menyesuaikan nominal,
     * misalnya bila tarif belum tercantum di SK atau ada kebijakan khusus.
     */
    protected static function lockFee(RoomBooking $booking, ?float $fee): void
    {
        if (! $booking->isExternal()) {
            return;
        }

        $hasil = RentalFeeService::calculate($booking->room, $booking->start_at, $booking->end_at);
        $rincian = static::breakdownFrom($hasil);
        $total = $hasil['total'];

        if ($fee !== null && abs($fee - $total) > 0.009) {
            $rincian['penyesuaian'] = [
                'dari' => $total,
                'menjadi' => $fee,
                'oleh' => Auth::user()?->name,
            ];
            $total = $fee;
        }

        $booking->forceFill([
            'fee_amount' => $total,
            'rental_decree_id' => $hasil['decree']?->getKey(),
            'fee_breakdown' => $rincian,
            'payment_status' => $total > 0 ? PaymentStatus::BelumBayar : null,
        ])->save();
    }

    protected static function breakdownFrom(array $hasil): array
    {
        return [
            'hari' => $hasil['rincian'],
            'sk' => $hasil['decree']?->number,
            'pesan' => $hasil['pesan'],
        ];
    }

    /** Pemesan atau petugas mengunggah bukti bayar */
    public static function canSubmitPayment(RoomBooking $booking, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || $booking->status !== BookingStatus::Disetujui || ! $booking->requiresPayment() || $booking->isPaid()) {
            return false;
        }

        return $user->hasAnyRole(static::APPROVER_ROLES)
            || (int) $booking->user_id === (int) $user->getKey()
            || (int) $booking->created_by === (int) $user->getKey();
    }

    public static function canConfirmPayment(RoomBooking $booking, ?User $user = null): bool
    {
        $user ??= Auth::user();

        return (bool) $user?->hasAnyRole(static::APPROVER_ROLES)
            && $booking->status === BookingStatus::Disetujui
            && $booking->requiresPayment()
            && ! $booking->isPaid();
    }

    /** @param  string  $path  berkas sementara di disk local */
    public static function submitPaymentProof(RoomBooking $booking, string $path, ?string $note = null): void
    {
        $booking->addMedia(\Illuminate\Support\Facades\Storage::disk('local')->path($path))
            ->toMediaCollection('bukti_bayar');

        $booking->forceFill([
            'payment_status' => PaymentStatus::MenungguVerifikasi,
            'payment_note' => $note,
        ])->save();

        NotifyService::toRoles(
            ['kabag_sarpras'],
            'Bukti bayar sewa diunggah',
            $booking->booking_number.' — '.RentalFeeService::rupiah($booking->fee_amount).' · '.$booking->borrower_name,
            RoomBookingResource::getUrl('view', ['record' => $booking]),
            'warning',
            'heroicon-o-banknotes',
        );
    }

    public static function confirmPayment(RoomBooking $booking, ?string $note = null): void
    {
        $booking->forceFill([
            'payment_status' => PaymentStatus::Lunas,
            'paid_at' => now(),
            'payment_verified_by' => Auth::id(),
            'payment_note' => $note ?? $booking->payment_note,
            // Nomor kuitansi terbit sekali, saat pertama kali dikonfirmasi lunas
            'receipt_number' => $booking->receipt_number ?? NumberSequence::format('kuitansi', 'KWT'),
        ])->save();

        NotifyService::toUser(
            $booking->user_id ?? $booking->created_by,
            'Pembayaran sewa diterima',
            $booking->booking_number.' — '.RentalFeeService::rupiah($booking->fee_amount)
                .' telah lunas. Kuitansi '.$booking->receipt_number.' dapat dicetak dari halaman reservasi.',
            RoomBookingResource::getUrl('view', ['record' => $booking]),
            'success',
            'heroicon-o-banknotes',
        );
    }

    /** Tagihan dan kuitansi hanya untuk sewa pihak luar yang sudah disetujui */
    public static function canPrintInvoice(RoomBooking $booking): bool
    {
        return $booking->status === BookingStatus::Disetujui && $booking->requiresPayment();
    }

    public static function canPrintReceipt(RoomBooking $booking): bool
    {
        return static::canPrintInvoice($booking) && $booking->isPaid() && filled($booking->receipt_number);
    }

    /** Alasan pelengkap belum boleh diserahkan, atau null bila boleh */
    public static function handOutBlockedReason(RoomBooking $booking): ?string
    {
        if ($booking->requiresPayment() && ! $booking->isPaid()) {
            return 'Biaya sewa belum lunas.';
        }

        return null;
    }

    /* ------------------------------ Pelengkap ------------------------------ */

    /**
     * Rentang pinjam pelengkap: sama dengan rentang ruang terpakai,
     * karena kursi dan sound dipakai sejak persiapan sampai beres-beres.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function equipmentWindow(
        ?int $roomId,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
    ): array {
        return static::occupancy($roomId ? Room::find($roomId) : null, $start, $end);
    }

    /** Kelompok barang yang bisa dipilih: boleh dipinjam, di luar laboratorium */
    public static function equipmentGroups(
        ?int $roomId,
        CarbonInterface|string|null $start,
        CarbonInterface|string|null $end,
        ?int $exceptLoanId = null,
        ?User $borrower = null,
    ): Collection {
        if (blank($start) || blank($end)) {
            return collect();
        }

        [$mulai, $selesai] = static::equipmentWindow($roomId, $start, $end);

        if ($selesai->lte($mulai)) {
            return collect();
        }

        return LoanService::availableGroups($mulai, $selesai, $exceptLoanId, null, $borrower, true);
    }

    /**
     * Terjemahkan keranjang pelengkap menjadi [asset_id => jumlah].
     *
     * @return array{items: array<int, int>, errors: array<string>}
     */
    public static function resolveEquipment(
        array $cart,
        ?int $roomId,
        CarbonInterface|string $start,
        CarbonInterface|string $end,
        ?int $exceptLoanId = null,
        ?User $borrower = null,
    ): array {
        if (empty(array_filter($cart, fn ($r) => filled($r['group_key'] ?? null)))) {
            return ['items' => [], 'errors' => []];
        }

        [$mulai, $selesai] = static::equipmentWindow($roomId, $start, $end);

        $hasil = LoanService::resolveGroupSelection($cart, $mulai, $selesai, $exceptLoanId, null, $borrower, true);

        return ['items' => $hasil['items'], 'errors' => $hasil['errors']];
    }

    /** Peminjam untuk aturan "Dosen Saja": akun pemesan internal */
    public static function borrowerFor(?RoomBooking $booking = null, ?string $borrowerType = null): ?User
    {
        $jenis = $borrowerType ?? $booking?->borrower_type?->value ?? BorrowerType::Internal->value;

        if ($jenis === BorrowerType::Eksternal->value) {
            return null;
        }

        return $booking?->user ?? Auth::user();
    }

    /**
     * Samakan peminjaman pelengkap dengan isi keranjang.
     *
     * - Ada barang, belum ada pinjaman  : buat pinjaman berstatus Diajukan
     *   (barang langsung terkunci sejak reservasi diajukan)
     * - Ada barang, sudah ada pinjaman  : perbarui jadwal, peminjam, dan barangnya
     * - Keranjang kosong                : batalkan pinjaman yang ada
     *
     * @param  array<int, int>  $items  [asset_id => jumlah]
     */
    public static function syncEquipment(RoomBooking $booking, array $items): void
    {
        DB::transaction(function () use ($booking, $items) {
            $booking->loadMissing('room');
            $loan = static::equipmentLoan($booking);

            if (empty($items)) {
                if ($loan && in_array($loan->status, [LoanStatus::Diajukan, LoanStatus::Disetujui], true)) {
                    $loan->forceFill(['status' => LoanStatus::Batal])->save();
                }

                $booking->forceFill(['loan_id' => null])->saveQuietly();

                return;
            }

            [$mulai, $selesai] = static::equipmentWindow($booking->room_id, $booking->start_at, $booking->end_at);

            $data = [
                'borrower_type' => $booking->borrower_type,
                'user_id' => $booking->user_id,
                'borrower_name' => $booking->borrower_name,
                'borrower_institution' => $booking->borrower_institution,
                'borrower_phone' => $booking->borrower_phone,
                'borrower_email' => $booking->borrower_email,
                'purpose' => 'Pelengkap reservasi '.$booking->booking_number.': '.$booking->title,
                'purpose_type' => LoanPurpose::Kegiatan,
                'use_location' => $booking->room?->name,
                'start_at' => $mulai,
                'end_at' => $selesai,
            ];

            if (! $loan || in_array($loan->status, [LoanStatus::Ditolak, LoanStatus::Batal], true)) {
                // Penyetuju sudah diberi tahu lewat notifikasi reservasi
                LoanObserver::$muteCreatedNotification = true;

                try {
                    $loan = Loan::create($data + [
                        'status' => $booking->status === BookingStatus::Disetujui
                            ? LoanStatus::Disetujui
                            : LoanStatus::Diajukan,
                        'approved_by' => $booking->status === BookingStatus::Disetujui ? $booking->approved_by : null,
                        'approved_at' => $booking->status === BookingStatus::Disetujui ? $booking->approved_at : null,
                    ]);
                } finally {
                    LoanObserver::$muteCreatedNotification = false;
                }

                $booking->forceFill(['loan_id' => $loan->getKey()])->saveQuietly();
            } else {
                $loan->forceFill($data)->save();
            }

            LoanService::syncItems($loan, $items);
        });
    }

    /** Pinjaman pelengkap, tanpa scope visibilitas */
    public static function equipmentLoan(RoomBooking $booking): ?Loan
    {
        if (! $booking->loan_id) {
            return null;
        }

        return Loan::query()
            ->withoutGlobalScope(LoanVisibilityScope::class)
            ->find($booking->loan_id);
    }

    /** Petugas serah terima pelengkap: penyetuju dan penanggung jawab ruang */
    public static function canHandleEquipment(RoomBooking $booking, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(static::APPROVER_ROLES)
            || ($booking->room?->pic_user_id && (int) $booking->room->pic_user_id === (int) $user->getKey());
    }

    /* ------------------------------ Pembantu ------------------------------- */

    protected static function notifyApprovers(RoomBooking $booking, string $judul, string $isi, string $warna): void
    {
        $url = RoomBookingResource::getUrl('view', ['record' => $booking]);
        $pj = $booking->room?->pic_user_id;

        // Internal dengan penanggung jawab ruang: cukup ke penanggung jawab
        if (! $booking->isExternal() && $pj && (int) $pj !== (int) Auth::id()) {
            NotifyService::toUser($pj, $judul, $isi, $url, $warna, 'heroicon-o-calendar-days');

            return;
        }

        NotifyService::toRoles(['kabag_sarpras'], $judul, $isi, $url, $warna, 'heroicon-o-calendar-days');
    }

    protected static function approverRoleName(): string
    {
        $user = Auth::user();

        foreach (static::APPROVER_ROLES as $peran) {
            if ($user?->hasRole($peran)) {
                return $peran;
            }
        }

        return 'pj_ruang';
    }

    /** Pilihan jenis kegiatan untuk ruang tertentu */
    public static function typeOptions(?int $roomId): array
    {
        $room = $roomId ? Room::find($roomId) : null;
        $jenis = $room ? $room->allowedBookingTypes() : BookingType::cases();

        return collect($jenis)
            ->mapWithKeys(fn (BookingType $t) => [$t->value => $t->getLabel()])
            ->all();
    }

    /** Pemesan eksternal hanya bisa dicatat oleh penyetuju */
    public static function canRecordExternal(?User $user = null): bool
    {
        $user ??= Auth::user();

        return (bool) $user?->hasAnyRole(static::APPROVER_ROLES);
    }

    public static function borrowerTypeDefault(): string
    {
        return BorrowerType::Internal->value;
    }
}
