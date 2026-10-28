<?php

namespace App\Filament\Resources\Consumables\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Consumables\ConsumableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConsumable extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = ConsumableResource::class;
}