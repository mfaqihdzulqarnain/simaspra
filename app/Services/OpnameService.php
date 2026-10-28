<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\OpnameItemStatus;
use App\Enums\LoanStatus;
use App\Enums\OpnameStatus;
use App\Models\Asset;
use App\Models\LoanItem;
use App\Models\Scopes\LoanVisibilityScope;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OpnameService
{
    /** Buka periode — bangkitkan daftar periksa dari aset dalam cakupan */
    public static function start(StockOpname $opname): int
    {
        return DB::transaction(function () use ($opname) {
            $count = 0;

            if ($opname->items()->doesntExist()) {
                $count = $opname->generateItems();
            }

            $opname->forceFill(['status' => OpnameStatus::Berjalan])->save();

            return $count;
        });
    }

    /**
     * Catat hasil scan QR.
     * Dipanggil dari halaman scan maupun dari panel.
     */
    public static function scan(
        StockOpname $opname,
        string $publicCode,
        ?int $foundRoomId = null,
        ?AssetCondition $condition = null,
        ?string $note = null,
        ?int $foundQuantity = null,
    ): array {
        if (! $opname->status->acceptsScan()) {
            return ['ok' => false, 'message' => 'Periode opname tidak sedang berjalan.'];
        }

        $asset = Asset::query()->allUnits()->byPublicCode($publicCode)->first();

        if (! $asset) {
            return ['ok' => false, 'message' => 'Kode aset tidak dikenali.'];
        }

        $item = $opname->items()->where('asset_id', $asset->getKey())->first();

        // Aset ditemukan tapi di luar cakupan opname ini
        if (! $item) {
            $item = StockOpnameItem::create([
                'stock_opname_id' => $opname->getKey(),
                'asset_id' => $asset->getKey(),
                'expected_room_id' => $asset->room_id,
                'expected_quantity' => $asset->isCollective() ? (int) $asset->quantity : null,
                'status' => OpnameItemStatus::TidakTercatat,
            ]);
        }

        $item->recordScan($foundRoomId, $condition, $note, $foundQuantity);

        return [
            'ok' => true,
            'message' => $asset->code.' — '.$item->fresh()->status->getLabel(),
            'asset' => $asset,
            'item' => $item->fresh(),
        ];
    }

    /** Catat barang yang ditemukan tapi belum terdaftar sama sekali */
    public static function recordUnregistered(
        StockOpname $opname,
        string $name,
        ?int $roomId,
        ?string $note = null,
    ): StockOpnameItem {
        return StockOpnameItem::create([
            'stock_opname_id' => $opname->getKey(),
            'asset_id' => null,
            'unregistered_name' => $name,
            'found_room_id' => $roomId,
            'status' => OpnameItemStatus::TidakTercatat,
            'note' => $note,
            'checked_by' => Auth::id(),
            'checked_at' => now(),
        ]);
    }

    /**
     * Tutup periode.
     * $applyChanges: terapkan temuan lokasi & kondisi ke data aset.
     */
    public static function close(StockOpname $opname, bool $applyChanges = true): array
    {
        return DB::transaction(function () use ($opname, $applyChanges) {
            $applied = 0;

            if ($applyChanges) {
                $opname->items()
                    ->whereNotNull('asset_id')
                    ->whereNotNull('checked_at')
                    ->with('asset')
                    ->get()
                    ->each(function (StockOpnameItem $item) use (&$applied) {
                        $item->applyToAsset();
                        $applied++;
                    });
            }

            $opname->close();

            return [
                'applied' => $applied,
                'summary' => $opname->fresh()->summary,
            ];
        });
    }

    /** Ringkasan untuk berita acara */
    public static function report(StockOpname $opname): array
    {
        $items = $opname->items()->with(['asset.category', 'expectedRoom', 'foundRoom'])->get();

        return [
            'total' => $items->count(),
            'diperiksa' => $items->whereNotNull('checked_at')->count(),
            'ditemukan' => $items->where('status', OpnameItemStatus::Ditemukan)->count(),
            'tidak_ditemukan' => $items->where('status', OpnameItemStatus::TidakDitemukan)->count(),
            'beda_lokasi' => $items->where('status', OpnameItemStatus::BedaLokasi)->count(),
            'beda_kondisi' => $items->where('status', OpnameItemStatus::BedaKondisi)->count(),
            'beda_jumlah' => $items->where('status', OpnameItemStatus::BedaJumlah)->count(),
            'tidak_tercatat' => $items->where('status', OpnameItemStatus::TidakTercatat)->count(),
            'selisih' => $items->filter(fn ($i) => $i->status->isDiscrepancy()),
        ];
    }

    /**
     * Unit aset kolektif yang sedang di tangan peminjam.
     * Ditampilkan ke petugas agar ikut dihitung sebagai ditemukan.
     */
    public static function unitsOnLoan(Asset $asset): int
    {
        return (int) LoanItem::query()
            ->where('asset_id', $asset->getKey())
            ->whereNull('returned_at')
            ->whereHas('loan', fn ($q) => $q
                ->withoutGlobalScope(LoanVisibilityScope::class)
                ->whereIn('status', [LoanStatus::Dipinjam->value, LoanStatus::Terlambat->value]))
            ->sum('quantity');
    }

    /** Normalkan nilai kondisi dari form, apa pun bentuknya */
    public static function toCondition(mixed $value): ?AssetCondition
    {
        if ($value instanceof AssetCondition) {
            return $value;
        }

        return filled($value) ? AssetCondition::tryFrom($value) : null;
    }

    public static function activeFor(Asset $asset): ?StockOpname
    {
        return StockOpname::query()
            ->where('status', OpnameStatus::Berjalan)
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', $asset->unit_id))
            ->where(function ($q) use ($asset) {
                $q->whereNull('building_id');

                if ($asset->room?->building_id) {
                    $q->orWhere('building_id', $asset->room->building_id);
                }
            })
            ->latest('period_start')
            ->first();
    }

    /** Baris periksa aset ini pada periode tertentu */
    public static function itemFor(StockOpname $opname, Asset $asset): ?StockOpnameItem
    {
        return $opname->items()->where('asset_id', $asset->getKey())->first();
    }
}
