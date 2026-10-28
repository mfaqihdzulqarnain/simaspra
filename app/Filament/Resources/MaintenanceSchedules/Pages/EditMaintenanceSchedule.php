<?php

namespace App\Filament\Resources\MaintenanceSchedules\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMaintenanceSchedule extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = MaintenanceScheduleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['asset_id'] ?? null)) {
            $data['asset_category_id'] = null;
        } else {
            $data['asset_id'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),

            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }
}