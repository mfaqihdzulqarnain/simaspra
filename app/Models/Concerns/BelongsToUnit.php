<?php

namespace App\Models\Concerns;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToUnit
{
    public static function bootBelongsToUnit(): void
    {
        static::addGlobalScope('unit', function (Builder $query) {
            $user = Auth::user();

            // Console, queue, seeder: tanpa auth, tanpa batasan
            if (! $user) {
                return;
            }

            if ($user->canSeeAllUnits()) {
                return;
            }

            $model = $query->getModel();

            // Model boleh menentukan aturannya sendiri. Kembalikan true
            // bila sudah ditangani, false untuk memakai penyaringan unit.
            if (method_exists($model, 'applyUnitVisibility')
                && $model::applyUnitVisibility($query, $user)) {
                return;
            }

            // Punya akses tapi belum ditetapkan unitnya: jangan bocorkan apa pun
            if (! $user->unit_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(
                $model->getTable() . '.unit_id',
                $user->unit_id
            );
        });

        static::creating(function (Model $model) {
            if (blank($model->unit_id) && Auth::check() && Auth::user()->unit_id) {
                $model->unit_id = Auth::user()->unit_id;
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Lewati pembatasan unit, untuk laporan lintas unit */
    public function scopeAllUnits(Builder $query): Builder
    {
        return $query->withoutGlobalScope('unit');
    }
}