<?php

namespace App\Filament\Resources\MaintenanceSchedules\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceSchedule extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = MaintenanceScheduleResource::class;

    /** Pastikan hanya satu cakupan yang terisi */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['asset_id'] ?? null)) {
            $data['asset_category_id'] = null;
        } else {
            $data['asset_id'] = null;
        }

        return $data;
    }
}