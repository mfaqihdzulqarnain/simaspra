<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Siapa melihat pinjaman siapa.
 *
 *   Sarpras, Wadir, Pimpinan, Super Admin  → seluruh pinjaman
 *   Laboran                                → pinjaman di lab-nya + pinjamannya sendiri
 *   Selain itu                             → hanya pinjaman miliknya
 *
 * Console, scheduler, dan antrean berjalan tanpa login sehingga tidak disaring.
 */
class LoanVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->canSeeAllUnits()) {
            return;
        }

        $table = $model->getTable();

        if ($user->hasRole('laboran')) {
            $labIds = $user->laboratoryIds();

            $builder->where(function (Builder $q) use ($table, $labIds, $user) {
                $q->where("{$table}.user_id", $user->getKey());

                if (! empty($labIds)) {
                    $q->orWhereIn("{$table}.laboratory_id", $labIds);
                }
            });

            return;
        }

        $builder->where("{$table}.user_id", $user->getKey());
    }
}