<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\User;
use App\Services\ConsumableLoanService;
use App\Services\LoanService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateLoan extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = LoanResource::class;

    /** Hasil terjemahan keranjang: [asset_id => jumlah] */
    protected array $assetItems = [];

    /** [['consumable_id' => .., 'quantity' => ..], ...] */
    protected array $consumableRows = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->consumableRows = $data['consumable_cart'] ?? [];

        // Periksa alat massal lebih dulu agar tidak ada peminjaman setengah jadi
        $errors = ConsumableLoanService::check($this->consumableRows);

        if (! empty($errors)) {
            $this->tolak('Alat massal tidak mencukupi', $errors, 'data.consumable_cart');
        }

        $this->assetItems = $this->terjemahkanKeranjang($data);

        unset($data['cart'], $data['consumable_cart'], $data['asset_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        LoanService::syncItems($record, $this->assetItems);

        if (! empty($this->consumableRows)) {
            // Sudah diperiksa di atas; kesalahan di sini hanya terjadi bila
            // stok diambil orang lain pada detik yang sama
            $errors = ConsumableLoanService::sync($record, $this->consumableRows);

            if (! empty($errors)) {
                Notification::make()
                    ->warning()
                    ->title('Alat massal gagal dicatat')
                    ->body(implode(' ', $errors).' Edit peminjaman ini untuk menyesuaikan jumlahnya.')
                    ->persistent()
                    ->send();
            }
        }
    }

    protected function terjemahkanKeranjang(array $data): array
    {
        $hasil = LoanService::resolveGroupSelection(
            $data['cart'] ?? [],
            $data['start_at'],
            $data['end_at'],
            null,
            $data['laboratory_id'] ?? null,
            isset($data['user_id']) ? User::find($data['user_id']) : null,
        );

        if (! empty($hasil['errors'])) {
            $this->tolak('Alat tidak mencukupi', $hasil['errors'], 'data.cart');
        }

        if (empty($hasil['items']) && empty(ConsumableLoanService::normalize($this->consumableRows))) {
            throw ValidationException::withMessages([
                'data.cart' => 'Pilih minimal satu alat, baik berstiker QR maupun alat massal.',
            ]);
        }

        return $hasil['items'];
    }

    /** Tampilkan notifikasi lalu hentikan penyimpanan */
    protected function tolak(string $judul, array $errors, string $field): never
    {
        Notification::make()
            ->danger()
            ->title($judul)
            ->body(implode(' ', $errors))
            ->persistent()
            ->send();

        throw ValidationException::withMessages([
            $field => implode(' ', $errors),
        ]);
    }
}
