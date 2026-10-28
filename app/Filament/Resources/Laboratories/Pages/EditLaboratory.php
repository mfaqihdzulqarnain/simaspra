<?php

namespace App\Filament\Resources\Laboratories\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Laboratories\LaboratoryResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditLaboratory extends EditRecord
{
    use RedirectsToIndex;
    
    protected static string $resource = LaboratoryResource::class;

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
