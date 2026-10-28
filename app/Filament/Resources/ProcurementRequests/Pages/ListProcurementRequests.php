<?php

namespace App\Filament\Resources\ProcurementRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Resources\ProcurementRequests\ProcurementRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProcurementRequests extends ListRecords
{
    protected static string $resource = ProcurementRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Pengajuan')->icon('heroicon-s-plus')->color('green'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),

            'draf' => Tab::make('Draf')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    RequestStatus::Draf->value,
                    RequestStatus::Revisi->value,
                ])),

            'proses' => Tab::make('Diproses')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    RequestStatus::VerifikasiSarpras->value,
                    RequestStatus::MenungguWadir->value,
                ])),

            'disetujui' => Tab::make('Disetujui')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    RequestStatus::Disetujui->value,
                    RequestStatus::Disalurkan->value,
                    RequestStatus::Selesai->value,
                ])),

            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RequestStatus::Ditolak)),
        ];
    }
}