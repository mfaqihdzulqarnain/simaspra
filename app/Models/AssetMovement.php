<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AssetMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'from_unit_id',
        'to_unit_id',
        'from_room_id',
        'to_room_id',
        'moved_at',
        'reason',
        'document_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $movement) {
            $movement->created_by ??= Auth::id();
            $movement->moved_at ??= now()->toDateString();
        });

        // Pindahkan posisi aset setelah mutasi tercatat
        static::created(function (self $movement) {
            $movement->asset?->forceFill([
                'unit_id' => $movement->to_unit_id ?? $movement->asset->unit_id,
                'room_id' => $movement->to_room_id ?? $movement->asset->room_id,
            ])->save();
        });
    }

    /* ------------------------------- Relasi -------------------------------- */

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
