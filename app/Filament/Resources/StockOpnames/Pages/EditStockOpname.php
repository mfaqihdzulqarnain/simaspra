<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Enums\OpnameStatus;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStockOpname extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = StockOpnameResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->record->status === OpnameStatus::Draf, 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
            ViewAction::make(),
            DeleteAction::make(),

        ];
    }
}