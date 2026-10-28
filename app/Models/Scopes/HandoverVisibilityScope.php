<?php

namespace App\Models\Scopes;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Siapa melihat berita acara siapa.
 *
 *   Sarpras, Wadir, Pimpinan, Super Admin → semua
 *   Laboran → berita acara peminjaman di lab yang diampunya, ditambah yang ia buat, serahkan, atau terima
 *   Lainnya → berita acara unitnya, ditambah yang ia buat, serahkan, atau terima
 *
 * Berlaku juga untuk route cetak: berita acara di luar wewenang menghasilkan 404.
 */
class HandoverVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->canSeeAllUnits()) {
            return;
        }

        $table = $model->getTable();

        $builder->where(function (Builder $q) use ($table, $user) {
            $q->where("{$table}.created_by", $user->getKey())
                ->orWhere("{$table}.from_user_id", $user->getKey())
                ->orWhere("{$table}.to_user_id", $user->getKey());

            if ($user->hasRole('laboran')) {
                $labIds = $user->laboratoryIds();

                if (! empty($labIds)) {
                    $q->orWhere(fn (Builder $w) => $w
                        ->where("{$table}.reference_type", (new Loan)->getMorphClass())
                        ->whereIn("{$table}.reference_id", Loan::query()
                            ->withoutGlobalScopes()
                            ->whereIn('laboratory_id', $labIds)
                            ->select('id')));
                }

                return;
            }

            if ($user->unit_id) {
                $q->orWhere("{$table}.unit_id", $user->unit_id);
            }
        });
    }
}