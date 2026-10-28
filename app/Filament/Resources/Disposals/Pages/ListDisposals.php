<?php

namespace App\Filament\Resources\Disposals\Pages;

use App\Enums\DisposalStatus;
use App\Filament\Resources\Disposals\DisposalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDisposals extends ListRecords
{
    protected static string $resource = DisposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Usulkan Penghapusan')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'proses' => Tab::make('Dalam Proses')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    DisposalStatus::Usulan->value,
                    DisposalStatus::Pemeriksaan->value,
                    DisposalStatus::MenungguWadir->value,
                    DisposalStatus::Disetujui->value,
                ]))
                ->badge(fn () => static::getResource()::getModel()::query()
                    ->whereIn('status', [
                        DisposalStatus::Usulan->value,
                        DisposalStatus::Pemeriksaan->value,
                        DisposalStatus::MenungguWadir->value,
                        DisposalStatus::Disetujui->value,
                    ])->count())
                ->badgeColor('warning'),

            'dieksekusi' => Tab::make('Sudah Dieksekusi')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DisposalStatus::Dieksekusi)),

            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DisposalStatus::Ditolak)),

            'arsip' => Tab::make('Arsip')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DisposalStatus::Arsip)),

            'semua' => Tab::make('Semua'),
        ];
    }
}