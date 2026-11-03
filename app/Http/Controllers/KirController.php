<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\Sapra;
use App\Models\Consumable;
use Barryvdh\DomPDF\Facade\Pdf;

class KirController extends Controller
{
    public function print(Room $room)
    {
        abort_unless(auth()->check(), 401);

        $user = auth()->user();

        abort_unless($this->boleh($room), 403);

        $room->load(['building', 'unit', 'picUser']);

        $assets = $room->assets()
            ->allUnits()
            ->active()
            ->with('category')
            ->orderBy('code')
            ->get();

        // Barang & alat massal di ruang ini, ikut dicetak di tabel terpisah
        $consumables = Consumable::query()
            ->where('storage_room_id', $room->getKey())
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $pdf = Pdf::loadView('print.kir', [
            'room' => $room,
            'assets' => $assets,
            'total' => $assets->sum('acquisition_value'),
            'institution' => Sapra::institutionName(),
            'address' => Sapra::settings()?->address,
            'phone' => Sapra::settings()?->phone,
            'email' => Sapra::settings()?->email,
            'website' => Sapra::settings()?->website,
            'useLetterhead' => Sapra::usesLetterheadImage(),
            'letterhead' => Sapra::letterheadDataUri(),
            'logo' => Sapra::labelLogoPath()
                ? 'data:image/png;base64,' . base64_encode(file_get_contents(Sapra::labelLogoPath()))
                : null,
            'signatory' => Sapra::signatory('sarpras'),
            'consumables' => $consumables,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('KIR-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $room->code) . '.pdf');
    }

    /**
     * Yang boleh mencetak KIR sebuah ruang:
     * pengguna lintas unit, pemilik unit ruang, penanggung jawab ruang,
     * dan laboran laboratorium yang menaungi ruang itu.
     */
    protected function boleh(Room $room): bool
    {
        $user = auth()->user();

        if ($user->canSeeAllUnits()) {
            return true;
        }

        if ($room->unit_id && (int) $room->unit_id === (int) $user->unit_id) {
            return true;
        }

        if ((int) $room->pic_user_id === (int) $user->getKey()) {
            return true;
        }

        return $room->laboratory_id
            && in_array((int) $room->laboratory_id, array_map('intval', $user->laboratoryIds()), true);
    }
}
