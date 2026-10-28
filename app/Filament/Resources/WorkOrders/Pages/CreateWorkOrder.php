<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Enums\WorkOrderStatus;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkOrder extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = WorkOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = filled($data['assigned_to'] ?? null)
            ? WorkOrderStatus::Ditugaskan->value
            : WorkOrderStatus::Dilaporkan->value;

        return $data;
    }
}