<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Enums\OpnameStatus;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStockOpname extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = StockOpnameResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = OpnameStatus::Draf->value;

        return $data;
    }
}