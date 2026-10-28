<?php

namespace App\Http\Controllers;

use App\Models\RoomBooking;
use App\Services\RoomBookingService;
use App\Support\Sapra;
use App\Support\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Tagihan dan kuitansi sewa ruang pihak luar.
 * Hanya bisa dibuka oleh yang boleh melihat reservasinya.
 */
class RentalReceiptController extends Controller
{
    public function invoice(int $roomBooking)
    {
        $booking = $this->findBooking($roomBooking);

        abort_unless(RoomBookingService::canPrintInvoice($booking), 404);

        return $this->render($booking, 'tagihan');
    }

    public function receipt(int $roomBooking)
    {
        $booking = $this->findBooking($roomBooking);

        abort_unless(RoomBookingService::canPrintReceipt($booking), 404);

        return $this->render($booking, 'kuitansi');
    }

    /** Scope visibilitas reservasi ikut berlaku: yang tidak berhak mendapat 404 */
    protected function findBooking(int $id): RoomBooking
    {
        abort_unless(auth()->check(), 401);
        abort_unless(auth()->user()->can('ViewAny:RoomBooking'), 403);

        return RoomBooking::query()
            ->with(['room.building', 'rentalDecree', 'paymentVerifier'])
            ->findOrFail($id);
    }

    protected function render(RoomBooking $booking, string $jenis)
    {
        $logoPath = Sapra::labelLogoPath();

        $pdf = Pdf::loadView('print.sewa', [
            'booking' => $booking,
            'jenis' => $jenis,
            'terbilang' => Terbilang::rupiah($booking->fee_amount),
            'institution' => Sapra::institutionName(),
            'address' => Sapra::settings()?->address,
            'phone' => Sapra::settings()?->phone,
            'email' => Sapra::settings()?->email,
            'website' => Sapra::settings()?->website,
            'useLetterhead' => Sapra::usesLetterheadImage(),
            'letterhead' => Sapra::letterheadDataUri(),
            'logo' => $logoPath ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null,
            'signatory' => Sapra::signatory('sarpras'),
        ])->setPaper('a4', 'portrait');

        $nomor = $jenis === 'kuitansi' ? $booking->receipt_number : $booking->booking_number;

        return $pdf->stream(ucfirst($jenis).'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $nomor).'.pdf');
    }
}
