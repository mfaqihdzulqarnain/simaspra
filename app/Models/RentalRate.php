<?php

namespace App\Models;

use App\Enums\RateUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu tarif ruang di dalam SK: per hari, atau per sesi dengan batas jam */
class RentalRate extends Model
{
    protected $fillable = [
        'rental_decree_id',
        'room_id',
        'unit',
        'name',
        'session_hours',
        'price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit' => RateUnit::class,
            'session_hours' => 'decimal:1',
            'price' => 'decimal:2',
        ];
    }

    public function decree(): BelongsTo
    {
        return $this->belongsTo(RentalDecree::class, 'rental_decree_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** "Setengah hari (maks. 5 jam)" atau "Satu hari" */
    public function getDisplayLabelAttribute(): string
    {
        return $this->unit === RateUnit::Sesi && $this->session_hours
            ? $this->name.' (maks. '.rtrim(rtrim(number_format((float) $this->session_hours, 1, ',', '.'), '0'), ',').' jam)'
            : $this->name;
    }
}
