<?php

namespace App\Http\Controllers;

use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Models\Room;
use App\Services\QrService;
use App\Services\RoomBookingService;
use App\Support\Sapra;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Halaman QR ruang: identitas ruang dan jadwal pemakaiannya.
 * Terbuka tanpa login; hanya jam, nama kegiatan, dan status yang tampil.
 */
class PublicRoomController extends Controller
{
    public function show(string $code)
    {
        $room = Room::query()
            ->byPublicCode($code)
            ->with('building:id,name')
            ->firstOrFail();

        $jadwal = $room->is_bookable && $room->is_active
            ? RoomBookingService::publicSchedule($room, 7)
            : null;

        return view('public.room', [
            'room' => $room,
            'jadwal' => $jadwal,
            'theme' => Sapra::theme(),
            'appName' => Sapra::appName(),
            'pesanUrl' => $room->is_bookable && $room->is_active
                ? RoomBookingResource::getUrl('create').'?ruang='.$room->getKey()
                : null,
            'kirUrl' => auth()->check() ? route('kir.print', $room) : null,
        ]);
    }

    /** Papan QR untuk ditempel di pintu ruang (A5) */
    public function sign(Room $room)
    {
        abort_unless(auth()->check(), 401);

        $user = auth()->user();

        abort_unless(
            $user->canSeeAllUnits()
                || $room->unit_id === null
                || $room->unit_id === $user->unit_id
                || (int) $room->pic_user_id === (int) $user->getKey(),
            403
        );

        $room->load('building:id,name');

        $pdf = Pdf::loadView('print.room-qr', [
            'room' => $room,
            'qr' => QrService::dataUri($room->qrPayload(), 520),
            'url' => $room->public_url,
            'appName' => Sapra::appName(),
            'institution' => Sapra::institutionName(),
            'logo' => Sapra::labelLogoPath()
                ? 'data:image/png;base64,'.base64_encode(file_get_contents(Sapra::labelLogoPath()))
                : null,
        ])->setPaper('a5', 'portrait');

        return $pdf->stream('QR-Ruang-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $room->code).'.pdf');
    }
}