<?php

namespace App\Filament\Resources\ProcurementRequests\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\ProcurementRequests\ProcurementRequestResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProcurementRequest extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = ProcurementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}