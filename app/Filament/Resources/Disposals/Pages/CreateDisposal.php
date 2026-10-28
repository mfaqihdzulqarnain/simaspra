<?php

namespace App\Filament\Resources\Disposals\Pages;

use App\Enums\DisposalStatus;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Disposals\DisposalResource;
use App\Models\DisposalItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDisposal extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = DisposalResource::class;

    protected array $assetIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->assetIds = $data['asset_ids'] ?? [];
        unset($data['asset_ids']);

        $data['proposed_by'] = Auth::id();
        $data['status'] = DisposalStatus::Usulan->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Kondisi dan nilai buku diisi otomatis oleh booted() di DisposalItem
        foreach ($this->assetIds as $assetId) {
            DisposalItem::create([
                'disposal_id' => $this->record->getKey(),
                'asset_id' => $assetId,
            ]);
        }
    }
}