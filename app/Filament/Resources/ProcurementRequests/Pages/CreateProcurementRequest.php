<?php

namespace App\Filament\Resources\ProcurementRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\ProcurementRequests\ProcurementRequestResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProcurementRequest extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = ProcurementRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = Auth::id();
        $data['status'] = RequestStatus::Draf->value;

        return $data;
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}