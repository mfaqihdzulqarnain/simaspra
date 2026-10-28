<?php

namespace App\Filament\Resources\RoomBookings\Pages;

use App\Enums\BorrowerType;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Services\RoomBookingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateRoomBooking extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = RoomBookingResource::class;

    protected static ?string $title = 'Buat Reservasi Ruang';

    /** Pelengkap hasil terjemahan keranjang: [asset_id => jumlah] */
    protected array $pelengkap = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        // Akun peminjam eksternal memesan atas namanya sendiri
        if ($user->hasRole('peminjam_eksternal')) {
            $data['borrower_type'] = BorrowerType::Eksternal->value;
            $data['user_id'] = $user->getKey();
            $data['borrower_name'] = $user->name;
            $data['borrower_phone'] = $user->phone;
            $data['borrower_email'] = $user->email;
        }

        // Selain penyetuju, pemesanan selalu internal atas nama akun sendiri
        if (! RoomBookingService::canRecordExternal() && ! $user->hasRole('peminjam_eksternal')) {
            $data['borrower_type'] = BorrowerType::Internal->value;
            unset($data['borrower_name'], $data['borrower_institution'], $data['borrower_phone'], $data['borrower_email']);
        }

        $data['borrower_type'] ??= BorrowerType::Internal->value;

        // Pihak luar yang dicatat petugas tidak punya akun
        if ($data['borrower_type'] === BorrowerType::Eksternal->value && ! $user->hasRole('peminjam_eksternal')) {
            $data['user_id'] = null;
        }

        $errors = RoomBookingService::validate($data);

        if (! empty($errors)) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn ($pesan, $field) => ['data.'.$field => $pesan])->all()
            );
        }

        // Pelengkap: diperiksa sebelum apa pun disimpan
        $hasil = RoomBookingService::resolveEquipment(
            $data['pelengkap'] ?? [],
            (int) $data['room_id'],
            $data['start_at'],
            $data['end_at'],
            null,
            RoomBookingService::borrowerFor(null, $data['borrower_type']),
        );

        if (! empty($hasil['errors'])) {
            throw ValidationException::withMessages([
                'data.pelengkap' => 'Pelengkap tidak mencukupi: '.implode(' ', $hasil['errors']),
            ]);
        }

        $this->pelengkap = $hasil['items'];
        unset($data['pelengkap']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // Perkiraan biaya sewa pihak luar
        RoomBookingService::refreshFee($record);

        if (! empty($this->pelengkap)) {
            RoomBookingService::syncEquipment($record, $this->pelengkap);
        }

        RoomBookingService::notifyNew($record->refresh());
    }
}
