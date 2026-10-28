<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
// use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditWorkOrder extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = WorkOrderResource::class;

    /** Pekerjaan yang sudah selesai tidak boleh diubah */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->record->isOpen(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),

            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }
}