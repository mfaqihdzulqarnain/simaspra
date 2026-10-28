<?php

namespace App\Filament\Resources\RoomBookings\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use App\Services\RoomBookingService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class EditRoomBooking extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = RoomBookingResource::class;

    /** Pelengkap hasil terjemahan keranjang: [asset_id => jumlah] */
    protected array $pelengkap = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        // Jenis pemesan tidak diubah lewat edit
        $data['borrower_type'] = $record->borrower_type->value;

        $errors = RoomBookingService::validate(
            [...$record->only(['room_id', 'booking_type', 'start_at', 'end_at', 'attendees']), ...$data],
            $record->getKey(),
            baru: false,
        );

        if (! empty($errors)) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn ($pesan, $field) => ['data.'.$field => $pesan])->all()
            );
        }

        // Pelengkap: jumlah yang sudah dipegang reservasi ini dihitung sebagai miliknya
        $hasil = RoomBookingService::resolveEquipment(
            $data['pelengkap'] ?? [],
            (int) ($data['room_id'] ?? $record->room_id),
            $data['start_at'] ?? $record->start_at,
            $data['end_at'] ?? $record->end_at,
            $record->loan_id,
            RoomBookingService::borrowerFor($record),
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

    protected function afterSave(): void
    {
        $record = $this->getRecord()->refresh();

        RoomBookingService::refreshFee($record);
        RoomBookingService::syncEquipment($record, $this->pelengkap);
    }
}
