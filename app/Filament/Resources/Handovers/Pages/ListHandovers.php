<?php

namespace App\Filament\Resources\Handovers\Pages;

use App\Filament\Resources\Handovers\HandoverResource;
use Filament\Resources\Pages\ListRecords;

class ListHandovers extends ListRecords
{
    protected static string $resource = HandoverResource::class;
}