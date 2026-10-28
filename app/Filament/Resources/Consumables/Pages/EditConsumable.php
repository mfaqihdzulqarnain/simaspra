<?php

namespace App\Filament\Resources\Consumables\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Consumables\ConsumableResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditConsumable extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = ConsumableResource::class;

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