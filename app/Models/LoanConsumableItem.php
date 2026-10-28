<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris alat massal dalam satu peminjaman */
class LoanConsumableItem extends Model
{
    protected $fillable = [
        'loan_id',
        'consumable_id',
        'quantity',
        'returned_quantity',
        'damaged_quantity',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'damaged_quantity' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /** Belum kembali sama sekali */
    public function getOutstandingAttribute(): int
    {
        return max($this->quantity - (int) $this->returned_quantity - (int) $this->damaged_quantity, 0);
    }

    /** Hilang: tidak kembali, baik utuh maupun rusak (setelah pengembalian dicatat) */
    public function getLostQuantityAttribute(): int
    {
        if ($this->returned_quantity === null) {
            return 0;
        }

        return $this->outstanding;
    }
}