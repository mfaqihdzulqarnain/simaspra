<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajukan Peminjaman')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        $model = static::getResource()::getModel();

        return [
            'berjalan' => Tab::make('Sedang Dipinjam')
                ->modifyQueryUsing(fn (Builder $query) => $query->ongoing())
                ->badge(fn () => $model::query()->ongoing()->count())
                ->badgeColor('primary'),

            'menunggu' => Tab::make('Menunggu Persetujuan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LoanStatus::Diajukan))
                ->badge(fn () => $model::query()->where('status', LoanStatus::Diajukan)->count())
                ->badgeColor('warning'),

            'disetujui' => Tab::make('Disetujui')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LoanStatus::Disetujui))
                ->badge(fn () => $model::query()->where('status', LoanStatus::Disetujui)->count())
                ->badgeColor('info'),

            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LoanStatus::Ditolak)),

            'terlambat' => Tab::make('Terlambat')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', [
                        LoanStatus::Dipinjam->value,
                        LoanStatus::Terlambat->value,
                    ])
                    ->where('end_at', '<', now()))
                ->badge(fn () => $model::query()
                    ->whereIn('status', [
                        LoanStatus::Dipinjam->value,
                        LoanStatus::Terlambat->value,
                    ])
                    ->where('end_at', '<', now())
                    ->count())
                ->badgeColor('danger'),

            'selesai' => Tab::make('Dikembalikan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LoanStatus::Dikembalikan)),

            'semua' => Tab::make('Semua'),
        ];
    }
}