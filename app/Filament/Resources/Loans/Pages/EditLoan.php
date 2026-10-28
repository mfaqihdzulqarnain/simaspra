<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\User;
use App\Services\ConsumableLoanService;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class EditLoan extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = LoanResource::class;

    /** Hasil terjemahan keranjang: [asset_id => jumlah] */
    protected array $assetItems = [];

    /** [['consumable_id' => .., 'quantity' => ..], ...] */
    protected array $consumableRows = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $loanId = $this->getRecord()->getKey();

        $this->consumableRows = $data['consumable_cart'] ?? [];

        // Jumlah yang sudah dicadangkan peminjaman ini dihitung sebagai miliknya
        $errors = ConsumableLoanService::check($this->consumableRows, $loanId);

        if (! empty($errors)) {
            $this->tolak('Alat massal tidak mencukupi', $errors, 'data.consumable_cart');
        }

        $hasil = LoanService::resolveGroupSelection(
            $data['cart'] ?? [],
            $data['start_at'],
            $data['end_at'],
            $loanId,
            $data['laboratory_id'] ?? null,
            isset($data['user_id']) ? User::find($data['user_id']) : null,
        );

        if (! empty($hasil['errors'])) {
            $this->tolak('Alat tidak mencukupi', $hasil['errors'], 'data.cart');
        }

        // Sama seperti saat membuat: boleh hanya berisi alat massal
        if (empty($hasil['items']) && empty(ConsumableLoanService::normalize($this->consumableRows))) {
            throw ValidationException::withMessages([
                'data.cart' => 'Pilih minimal satu alat, baik berstiker QR maupun alat massal.',
            ]);
        }

        $this->assetItems = $hasil['items'];

        unset($data['cart'], $data['consumable_cart'], $data['asset_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        LoanService::syncItems($record, $this->assetItems);

        // Satu langkah: lepas cadangan lama dan pasang yang baru.
        // Bila gagal, isi alat massal yang lama tetap utuh.
        $errors = ConsumableLoanService::sync($record, $this->consumableRows);

        if (! empty($errors)) {
            Notification::make()
                ->warning()
                ->title('Alat massal tidak diubah')
                ->body(implode(' ', $errors).' Isi alat massal sebelumnya tetap dipertahankan.')
                ->persistent()
                ->send();
        }
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
