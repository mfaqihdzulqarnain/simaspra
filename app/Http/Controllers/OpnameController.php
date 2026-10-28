<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use App\Services\OpnameService;
use App\Support\Sapra;
use Barryvdh\DomPDF\Facade\Pdf;

class OpnameController extends Controller
{
    public function print(StockOpname $stockOpname)
    {
        abort_unless(auth()->check(), 401);

        $user = auth()->user();

        abort_unless(
            $user->canSeeAllUnits()
                || $stockOpname->unit_id === null
                || $stockOpname->unit_id === $user->unit_id,
            403
        );
        
        $stockOpname->load(['unit', 'building', 'creator']);

        $pdf = Pdf::loadView('print.opname', [
            'opname' => $stockOpname,
            'report' => OpnameService::report($stockOpname),
            'institution' => Sapra::institutionName(),
            'address' => Sapra::settings()?->address,
            'phone' => Sapra::settings()?->phone,
            'email' => Sapra::settings()?->email,
            'website' => Sapra::settings()?->website,
            'useLetterhead' => Sapra::usesLetterheadImage(),
            'letterhead' => Sapra::letterheadDataUri(),
            'logo' => Sapra::labelLogoPath()
                ? 'data:image/png;base64,'.base64_encode(file_get_contents(Sapra::labelLogoPath()))
                : null,
            'signatory' => Sapra::signatory('sarpras'),
            'wadir' => Sapra::signatory('wadir'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('BA-Opname-'.str_replace('/', '-', $stockOpname->code).'.pdf');
    }
}