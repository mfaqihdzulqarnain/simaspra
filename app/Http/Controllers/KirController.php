<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\Sapra;
use Barryvdh\DomPDF\Facade\Pdf;

class KirController extends Controller
{
    public function print(Room $room)
    {
        abort_unless(auth()->check(), 401);

        $user = auth()->user();

        abort_unless(
            $user->canSeeAllUnits()
                || $room->unit_id === null
                || $room->unit_id === $user->unit_id,
            403
        );

        $room->load(['building', 'unit', 'picUser']);

        $assets = $room->assets()
            ->allUnits()
            ->active()
            ->with('category')
            ->orderBy('code')
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
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('KIR-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $room->code) . '.pdf');
    }
}