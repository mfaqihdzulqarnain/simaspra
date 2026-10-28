<?php

namespace App\Filament\Resources\Laboratories\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Laboratories\LaboratoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLaboratory extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = LaboratoryResource::class;
}
