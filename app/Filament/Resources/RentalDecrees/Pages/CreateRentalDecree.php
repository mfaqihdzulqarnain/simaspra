<?php

namespace App\Filament\Resources\RentalDecrees\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\RentalDecrees\RentalDecreeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRentalDecree extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = RentalDecreeResource::class;

    protected static ?string $title = 'Tambah SK Tarif Sewa';
}
