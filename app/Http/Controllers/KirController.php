<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\Room;
use App\Support\Sapra;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Kartu Inventaris Ruangan.
 *
 * Dua varian lewat parameter ?nilai=1 :
 *   tanpa nilai  — kolom Jml (jumlah unit), tanpa nominal. Untuk semua yang berhak
 *   dengan nilai — kolom Jml ditambah kolom Nilai dan Total Nilai. Hanya Sarpras dan Admin
 */
class KirController extends Controller
{
    /** Peran yang boleh melihat nominal nilai aset */
    public const PERAN_NILAI = ['kabag_sarpras', 'super_admin'];

    public function print(Request $request, Room $room)
    {
        abort_unless(auth()->check(), 401);
        abort_unless($this->boleh($room), 403);

        $denganNilai = $request->boolean('nilai');

        abort_if($denganNilai && ! static::bolehNilai(), 403);

        $room->load(['building:id,name', 'unit:id,name', 'laboratory:id,name', 'picUser:id,name,position']);

        $assets = $room->assets()
            ->with('category:id,name')
            ->orderBy('code')
            ->get();

        // Barang & alat massal yang disimpan di ruang ini
        $consumables = Consumable::query()
            ->where('storage_room_id', $room->getKey())
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $logoPath = Sapra::labelLogoPath();

        $pdf = Pdf::loadView('print.kir', [
            'room' => $room,
            'assets' => $assets,
            'consumables' => $consumables,
            'denganNilai' => $denganNilai,
            'totalUnit' => (int) $assets->sum(fn ($a) => max(1, (int) $a->quantity)),
            'totalNilai' => (float) $assets->sum('acquisition_value'),
            'institution' => Sapra::institutionName(),
            'address' => Sapra::settings()?->address,
            'phone' => Sapra::settings()?->phone,
            'email' => Sapra::settings()?->email,
            'useLetterhead' => Sapra::usesLetterheadImage(),
            'letterhead' => Sapra::letterheadDataUri(),
            'logo' => $logoPath ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null,
            'signatory' => Sapra::signatory('sarpras'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('KIR-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $room->code)
            .($denganNilai ? '' : '-tanpa-nilai').'.pdf');
    }

    /** Hanya Sarpras dan Admin yang boleh mencetak versi bernominal */
    public static function bolehNilai(?\App\Models\User $user = null): bool
    {
        return (bool) ($user ?? auth()->user())?->hasAnyRole(self::PERAN_NILAI);
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
