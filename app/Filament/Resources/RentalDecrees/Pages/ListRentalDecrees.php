<?php

namespace App\Filament\Resources\RentalDecrees\Pages;

use App\Filament\Resources\RentalDecrees\RentalDecreeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentalDecrees extends ListRecords
{
    protected static string $resource = RentalDecreeResource::class;

    public function getSubheading(): ?string
    {
        return 'SK yang berlaku adalah SK dengan tanggal berlaku paling akhir yang sudah dimulai. SK lama tetap tersimpan sebagai arsip.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah SK Tarif')
                ->icon('heroicon-s-plus'),
        ];
    }
}
