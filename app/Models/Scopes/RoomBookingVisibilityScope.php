<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Siapa melihat reservasi siapa.
 *
 *   Sarpras, Wadir, Pimpinan, Super Admin  → seluruh reservasi
 *   Penanggung jawab ruang                 → reservasi di ruangnya + miliknya sendiri
 *   Selain itu                             → hanya miliknya
 *
 * Jadwal Ruang (kalender) sengaja melepas scope ini agar semua orang
 * tahu kapan ruang terisi, tetapi hanya menampilkan judul dan jam.
 */
class RoomBookingVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->canSeeAllUnits()) {
            return;
        }

        $table = $model->getTable();

        $builder->where(function (Builder $q) use ($table, $user) {
            $q->where("{$table}.user_id", $user->getKey())
                ->orWhere("{$table}.created_by", $user->getKey())
                ->orWhereIn("{$table}.room_id", function ($sub) use ($user) {
                    $sub->select('id')
                        ->from('rooms')
                        ->where('pic_user_id', $user->getKey());
                });
        });
    }
}
