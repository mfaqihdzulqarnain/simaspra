<?php

namespace App\Services;

use App\Enums\DamageSeverity;
use App\Enums\StockMovementType;
use App\Models\Consumable;
use App\Models\ConsumableMovement;
use App\Models\DamageReport;
use App\Models\Loan;
use App\Models\LoanConsumableItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Peminjaman alat massal: dihitung per jumlah, tanpa kode QR.
 *
 * Ketersediaan berbasis stok saat ini (current_stock − on_loan),
 * bukan jadwal. Stok dicadangkan sejak permohonan dibuat dan
 * dilepas saat ditolak, dibatalkan, atau dikembalikan.
 *
 * Penting: jangan panggil sync() untuk peminjaman yang sudah ditolak
 * atau dibatalkan. Stoknya sudah dilepas, sehingga baris lamanya
 * tidak lagi mewakili cadangan yang sah.
 */
class ConsumableLoanService
{
    /**
     * Alat massal yang bisa dipilih.
     *
     * $exceptLoanId: saat mengedit, jumlah yang sudah dicadangkan oleh
     * peminjaman itu dihitung sebagai tersedia untuknya sendiri.
     * Setiap barang diberi atribut sementara `available_for`.
     */
    public static function available(?int $laboratoryId = null, ?int $exceptLoanId = null): Collection
    {
        $milikSendiri = static::reservedBy($exceptLoanId);

        return Consumable::query()
            ->loanable()
            ->when($laboratoryId, fn ($q) => $q->inLaboratory($laboratoryId))
            ->orderBy('name')
            ->get()
            ->each(fn (Consumable $c) => $c->setAttribute(
                'available_for',
                $c->available_stock + ($milikSendiri[$c->getKey()] ?? 0),
            ))
            ->filter(fn (Consumable $c) => $c->available_for > 0)
            ->values();
    }

    /** Jumlah yang boleh dipinjam untuk satu barang */
    public static function availableQuantity(?Consumable $item, ?int $exceptLoanId = null): int
    {
        if (! $item) {
            return 0;
        }

        return $item->available_stock + (static::reservedBy($exceptLoanId)[$item->getKey()] ?? 0);
    }

    /**
     * Periksa permintaan tanpa menyimpan apa pun.
     * Dipakai sebelum peminjaman dibuat agar tidak ada data setengah jadi.
     *
     * @param  array  $rows  [['consumable_id' => 1, 'quantity' => 12], ...]
     * @return array<string>
     */
    public static function check(array $rows, ?int $exceptLoanId = null): array
    {
        $diminta = static::normalize($rows);

        if (empty($diminta)) {
            return [];
        }

        $milikSendiri = static::reservedBy($exceptLoanId);

        $barang = Consumable::query()
            ->whereIn('id', array_keys($diminta))
            ->get()
            ->keyBy('id');

        return static::validate($diminta, $barang, $milikSendiri);
    }

    /**
     * Samakan isi alat massal sebuah peminjaman dengan $rows.
     *
     * Semua atau tidak sama sekali: bila satu baris gagal, tidak ada
     * yang berubah dan daftar kesalahan dikembalikan. Aman dipakai untuk
     * peminjaman baru maupun saat mengedit.
     *
     * @param  array  $rows  [['consumable_id' => 1, 'quantity' => 12], ...]
     * @return array<string> daftar kesalahan, kosong bila berhasil
     */
    public static function sync(Loan $loan, array $rows): array
    {
        $diminta = static::normalize($rows);
        $errors = [];

        DB::transaction(function () use ($loan, $diminta, &$errors) {
            $lama = $loan->consumableItems()->get()->keyBy('consumable_id');

            if (empty($diminta) && $lama->isEmpty()) {
                return;
            }

            // Kunci baris agar dua petugas tidak mengambil stok yang sama
            $barang = Consumable::query()
                ->whereIn('id', array_unique([...array_keys($diminta), ...$lama->keys()->all()]))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $milikSendiri = $lama->map(fn (LoanConsumableItem $i) => (int) $i->quantity)->all();

            $errors = static::validate($diminta, $barang, $milikSendiri);

            if (! empty($errors)) {
                return;
            }

            // Lepas cadangan lama
            foreach ($lama as $consumableId => $item) {
                $b = $barang->get($consumableId);

                if ($b) {
                    $b->decrement('on_loan', min((int) $item->quantity, (int) $b->on_loan));
                }
            }

            $loan->consumableItems()
                ->whereNotIn('consumable_id', array_keys($diminta))
                ->delete();

            // Pasang cadangan baru
            foreach ($diminta as $consumableId => $jumlah) {
                LoanConsumableItem::updateOrCreate(
                    ['loan_id' => $loan->getKey(), 'consumable_id' => $consumableId],
                    ['quantity' => $jumlah],
                );

                $barang->get($consumableId)->increment('on_loan', $jumlah);
            }
        });

        return $errors;
    }

    /** Nama lama, tetap dipertahankan untuk pemanggil yang sudah ada */
    public static function attach(Loan $loan, array $rows): array
    {
        return static::sync($loan, $rows);
    }

    /**
     * Catat pengembalian alat massal.
     *
     * returned: kembali dalam keadaan baik
     * damaged : kembali tetapi rusak
     * Sisanya dianggap hilang. Rusak dan hilang mengurangi stok.
     *
     * @param  array  $rows  [loan_consumable_item_id => ['returned' => 10, 'damaged' => 2, 'note' => ...]]
     */
    public static function handBack(Loan $loan, array $rows): void
    {
        DB::transaction(function () use ($loan, $rows) {
            $items = $loan->consumableItems()->with('consumable')->get();

            foreach ($items as $item) {
                $input = $rows[$item->getKey()] ?? [];
                $jumlah = (int) $item->quantity;

                $rusak = min(max((int) ($input['damaged'] ?? 0), 0), $jumlah);
                $kembali = array_key_exists('returned', $input) && $input['returned'] !== null && $input['returned'] !== ''
                    ? (int) $input['returned']
                    : $jumlah - $rusak;
                $kembali = min(max($kembali, 0), $jumlah - $rusak);

                $catatan = filled($input['note'] ?? null) ? trim($input['note']) : null;

                $item->update([
                    'returned_quantity' => $kembali,
                    'damaged_quantity' => $rusak,
                    'note' => $catatan,
                ]);

                $barang = $item->consumable;

                if (! $barang) {
                    continue;
                }

                // Seluruh yang keluar tidak lagi terhitung dipinjam
                $barang->decrement('on_loan', min($jumlah, (int) $barang->on_loan));

                // Rusak dan hilang mengurangi stok total
                $berkurang = $jumlah - $kembali;

                if ($berkurang > 0) {
                    $hilang = $berkurang - $rusak;

                    $rincian = collect([
                        $rusak > 0 ? $rusak.' rusak' : null,
                        $hilang > 0 ? $hilang.' hilang' : null,
                    ])->filter()->implode(', ');

                    ConsumableMovement::create([
                        'consumable_id' => $barang->getKey(),
                        'type' => StockMovementType::Keluar,
                        'quantity' => $berkurang,
                        'unit_id' => $loan->laboratory?->unit_id,
                        'moved_at' => now(),
                        'reference' => $loan->loan_number,
                        'note' => 'Pengembalian pinjaman: '.$rincian.($catatan ? '. '.$catatan : ''),
                    ]);

                    // Masuk menu Laporan Kerusakan agar bisa ditelusuri dan ditagih
                    DamageReport::create([
                        'consumable_id' => $barang->getKey(),
                        'quantity' => $berkurang,
                        'loan_id' => $loan->getKey(),
                        'reported_by' => Auth::id(),
                        'reporter_name' => Auth::user()?->name,
                        'description' => 'Alat massal saat pengembalian pinjaman '.$loan->loan_number
                            .': '.$rincian.' dari '.$jumlah.' '.$barang->unit_of_measure.' dipinjam.'
                            .($catatan ? ' '.$catatan : ''),
                        'severity' => $hilang > 0 ? DamageSeverity::Sedang : DamageSeverity::Ringan,
                    ]);
                }
            }
        });
    }

    /**
     * Lepaskan stok bila peminjaman ditolak atau dibatalkan.
     *
     * Baris item sengaja tidak dihapus agar halaman detail tetap
     * menunjukkan apa saja yang dulu diminta. Dipanggil LoanObserver
     * satu kali, tepat saat status berubah ke Ditolak atau Batal.
     */
    public static function release(Loan $loan): void
    {
        DB::transaction(function () use ($loan) {
            foreach ($loan->consumableItems()->with('consumable')->get() as $item) {
                $barang = $item->consumable;

                if ($barang) {
                    $barang->decrement('on_loan', min((int) $item->quantity, (int) $barang->on_loan));
                }
            }
        });
    }

    /* ------------------------------ Pembantu ------------------------------ */

    /**
     * Rapikan masukan dari form atau halaman pindai.
     * Baris kosong dibuang, baris ganda untuk barang sama dijumlahkan.
     *
     * @return array<int, int> [consumable_id => jumlah]
     */
    public static function normalize(array $rows): array
    {
        $hasil = [];

        foreach ($rows as $row) {
            $id = (int) ($row['consumable_id'] ?? 0);
            $jumlah = (int) ($row['quantity'] ?? 0);

            if ($id < 1 || $jumlah < 1) {
                continue;
            }

            $hasil[$id] = ($hasil[$id] ?? 0) + $jumlah;
        }

        return $hasil;
    }

    /** [consumable_id => jumlah] yang sedang dicadangkan oleh satu peminjaman */
    protected static function reservedBy(?int $loanId): array
    {
        if (! $loanId) {
            return [];
        }

        return LoanConsumableItem::query()
            ->where('loan_id', $loanId)
            ->pluck('quantity', 'consumable_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<string> */
    protected static function validate(array $diminta, Collection $barang, array $milikSendiri): array
    {
        $errors = [];

        foreach ($diminta as $id => $jumlah) {
            $item = $barang->get($id);

            if (! $item || ! $item->isLoanable()) {
                $errors[] = 'Salah satu alat massal tidak lagi tersedia untuk dipinjam.';

                continue;
            }

            $tersedia = $item->available_stock + ($milikSendiri[$id] ?? 0);

            if ($jumlah > $tersedia) {
                $errors[] = $item->name.' — diminta '.$jumlah
                    .', tersedia '.$tersedia.' '.$item->unit_of_measure.'.';
            }
        }

        return $errors;
    }
}
