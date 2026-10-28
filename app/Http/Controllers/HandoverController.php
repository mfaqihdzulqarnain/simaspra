<?php

namespace App\Http\Controllers;

use App\Models\Handover;
use App\Support\Sapra;
use Barryvdh\DomPDF\Facade\Pdf;

class HandoverController extends Controller
{
    public function print(Handover $handover)
    {
        abort_unless(auth()->check(), 401);
        // Wewenang diperiksa HandoverVisibilityScope saat route binding:
        // berita acara di luar wewenang tidak ditemukan (404).

        $handover->load([
            'items.asset.room',
            'items.asset.unit',
            'unit',
            'fromUser',
            'toUser',
            'reference',
            'reference.consumableItems.consumable',
            'reference.laboratory',
        ]);
        
        $pdf = Pdf::loadView('print.bast', [
            'handover' => $handover,
            'useLetterhead' => Sapra::usesLetterheadImage(),
            'letterhead' => Sapra::letterheadDataUri(),
            'institution' => Sapra::institutionName(),
            'address' => Sapra::settings()?->address,
            'phone' => Sapra::settings()?->phone,
            'email' => Sapra::settings()?->email,
            'website' => Sapra::settings()?->website,
            'logo' => Sapra::labelLogoPath()
                ? 'data:image/png;base64,'.base64_encode(file_get_contents(Sapra::labelLogoPath()))
                : null,
            'signatory' => Sapra::signatory('sarpras'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('BAST-'.str_replace('/', '-', $handover->number).'.pdf');
    }
}
