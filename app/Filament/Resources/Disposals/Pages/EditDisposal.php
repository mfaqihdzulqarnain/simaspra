<?php

namespace App\Filament\Resources\Disposals\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Disposals\DisposalResource;
use App\Models\DisposalItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDisposal extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = DisposalResource::class;

    protected array $assetIds = [];

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->record->isEditable(), 403);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->assetIds = $data['asset_ids'] ?? [];
        unset($data['asset_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->items()->whereNotIn('asset_id', $this->assetIds)->delete();

        $existing = $this->record->items()->pluck('asset_id')->all();

        foreach (array_diff($this->assetIds, $existing) as $assetId) {
            DisposalItem::create([
                'disposal_id' => $this->record->getKey(),
                'asset_id' => $assetId,
            ]);
        }

        $this->record->recalculateBookValue();
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