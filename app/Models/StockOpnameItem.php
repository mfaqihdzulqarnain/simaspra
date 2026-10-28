<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DamageSeverity;
use App\Enums\OpnameItemStatus;
use App\Enums\QuantityFollowUp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'asset_id',
        'unregistered_name',
        'expected_room_id',
        'found_room_id',
        'expected_quantity',
        'found_quantity',
        'quantity_follow_up',
        'status',
        'condition_found',
        'note',
        'checked_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OpnameItemStatus::class,
            'condition_found' => AssetCondition::class,
            'checked_at' => 'datetime',
            'expected_quantity' => 'integer',
            'found_quantity' => 'integer',
            'quantity_follow_up' => QuantityFollowUp::class,
        ];
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function expectedRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'expected_room_id');
    }

    public function foundRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'found_room_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /* -------------------------------- Scope -------------------------------- */

    public function scopeDiscrepancy(Builder $query): Builder
    {
        return $query->where('status', '!=', OpnameItemStatus::Ditemukan->value);
    }

    public function scopeUnchecked(Builder $query): Builder
    {
        return $query->whereNull('checked_at');
    }

    /* ------------------------------- Method -------------------------------- */

    /**
     * Catat hasil scan QR di lapangan.
     * Status ditentukan otomatis dari perbandingan lokasi, jumlah, dan kondisi.
     *
     * $foundQuantity hanya dipakai untuk aset kolektif; kosong berarti
     * jumlahnya sesuai catatan.
     */
    public function recordScan(
        ?int $foundRoomId,
        ?AssetCondition $condition = null,
        ?string $note = null,
        ?int $foundQuantity = null,
    ): void {
        $asset = $this->asset;
        $kolektif = (bool) $asset?->isCollective();

        $harapan = $kolektif ? ($this->expected_quantity ?? (int) $asset->quantity) : null;
        $temuan = $kolektif ? max(0, $foundQuantity ?? $harapan) : null;

        $status = match (true) {
            $foundRoomId && $this->expected_room_id && $foundRoomId !== $this->expected_room_id
                => OpnameItemStatus::BedaLokasi,

            $kolektif && $temuan !== $harapan
                => OpnameItemStatus::BedaJumlah,

            $condition && $asset && $condition !== $asset->condition
                => OpnameItemStatus::BedaKondisi,

            default => OpnameItemStatus::Ditemukan,
        };

        $this->forceFill([
            'found_room_id' => $foundRoomId,
            'condition_found' => $condition,
            'expected_quantity' => $harapan,
            'found_quantity' => $temuan,
            // Tindak lanjut hanya relevan bila jumlahnya memang berbeda
            'quantity_follow_up' => $kolektif && $temuan !== $harapan ? $this->quantity_follow_up : null,
            'status' => $status,
            'note' => $note ?? $this->note,
            'checked_by' => Auth::id(),
            'checked_at' => now(),
        ])->save();
    }

    /** Tandai tidak ditemukan; aset kolektif tercatat 0 unit */
    public function markNotFound(?string $note = null): void
    {
        $kolektif = (bool) $this->asset?->isCollective();

        $this->forceFill([
            'status' => OpnameItemStatus::TidakDitemukan,
            'found_room_id' => null,
            'expected_quantity' => $kolektif ? ($this->expected_quantity ?? (int) $this->asset->quantity) : null,
            'found_quantity' => $kolektif ? 0 : null,
            'note' => $note,
            'checked_by' => Auth::id(),
            'checked_at' => now(),
        ])->save();
    }

    /** Terapkan temuan ke data aset — hanya saat opname ditutup */
    public function applyToAsset(): void
    {
        $asset = $this->asset;

        if (! $asset) {
            return;
        }

        $changes = [];

        if ($this->found_room_id && $this->found_room_id !== $asset->room_id) {
            $changes['room_id'] = $this->found_room_id;
        }

        if ($this->condition_found && $this->condition_found !== $asset->condition) {
            $changes['condition'] = $this->condition_found;
        }

        // Tindak lanjut selisih jumlah aset kolektif
        if ($this->hasQuantityDifference() && $this->quantity_follow_up === QuantityFollowUp::Sesuaikan) {
            $changes += $this->quantityAdjustment($asset);
        }

        if ($changes) {
            $asset->forceFill($changes)->save();
        }

        if ($this->hasQuantityDifference() && $this->quantity_follow_up === QuantityFollowUp::Laporkan) {
            $this->reportMissingUnits($asset);
        }
    }

    public function hasQuantityDifference(): bool
    {
        return $this->expected_quantity !== null
            && $this->found_quantity !== null
            && $this->found_quantity !== $this->expected_quantity;
    }

    /** Selisih jumlah: negatif berarti kurang, positif berarti lebih */
    public function quantityDifference(): int
    {
        return (int) $this->found_quantity - (int) $this->expected_quantity;
    }

    /**
     * Perubahan data aset untuk "Sesuaikan jumlah aset".
     * Berkurang: nilai perolehan disesuaikan sebanding agar nilai per unit tetap.
     * Nol: aset berstatus Hilang, jumlah dan nilai tidak diubah.
     * Bertambah: jumlah naik, nilai perolehan tidak berubah.
     */
    protected function quantityAdjustment(Asset $asset): array
    {
        $temuan = (int) $this->found_quantity;

        if ($temuan <= 0) {
            return ['status' => AssetStatus::Hilang];
        }

        if ($temuan < (int) $asset->quantity) {
            return [
                'quantity' => $temuan,
                'acquisition_value' => round($asset->unitValue() * $temuan, 2),
            ];
        }

        return ['quantity' => $temuan];
    }

    /** "Buat laporan kehilangan": aset tidak diubah, unit yang hilang ditelusuri lewat laporan */
    protected function reportMissingUnits(Asset $asset): void
    {
        $hilang = (int) $this->expected_quantity - (int) $this->found_quantity;

        if ($hilang <= 0) {
            return;
        }

        DamageReport::create([
            'asset_id' => $asset->getKey(),
            'reported_by' => Auth::id(),
            'reporter_name' => Auth::user()?->name,
            'description' => 'Stock opname '.($this->opname?->name ?? '').': ditemukan '
                .$this->found_quantity.' dari '.$this->expected_quantity.' unit, '
                .$hilang.' unit tidak ditemukan.'.($this->note ? ' '.$this->note : ''),
            'severity' => DamageSeverity::Sedang,
        ]);
    }

    /* ------------------------------ Aksesor -------------------------------- */

    /** "115 / 120 unit" untuk aset kolektif */
    public function getQuantityLabelAttribute(): ?string
    {
        if ($this->expected_quantity === null) {
            return null;
        }

        return ($this->found_quantity ?? '—').' / '.$this->expected_quantity.' unit';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->asset?->display_name
            ?? $this->unregistered_name
            ?? '(tanpa nama)';
    }
}