<?php

namespace App\Filament\Resources\RentalDecrees\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\RentalDecrees\RentalDecreeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditRentalDecree extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = RentalDecreeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),

            DeleteAction::make()
                ->label('Hapus')
                ->modalDescription('SK dipindah ke arsip terhapus. Reservasi yang sudah ditagih tetap menyimpan nominal dan nomor SK-nya.'),
        ];
    }
}
