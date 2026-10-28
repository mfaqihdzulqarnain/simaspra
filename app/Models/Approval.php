<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class Approval extends Model
{
    use HasFactory;

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'step',
        'role_name',
        'user_id',
        'action',
        'note',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'action' => ApprovalAction::class,
            'acted_at' => 'datetime',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAction(Builder $query, ApprovalAction $action): Builder
    {
        return $query->where('action', $action);
    }

    /** Catat satu langkah persetujuan pada dokumen apa pun */
    public static function record(
        Model $approvable,
        ApprovalAction $action,
        int $step,
        string $roleName,
        ?string $note = null,
        ?int $userId = null,
    ): self {
        return static::create([
            'approvable_type' => $approvable->getMorphClass(),
            'approvable_id' => $approvable->getKey(),
            'step' => $step,
            'role_name' => $roleName,
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'note' => $note,
            'acted_at' => now(),
        ]);
    }
}
