<?php

namespace App\Filament\Resources\RoomBookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Pages\JadwalRuang;
use App\Filament\Resources\RoomBookings\RoomBookingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListRoomBookings extends ListRecords
{
    protected static string $resource = RoomBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('jadwal')
                ->label('Lihat Jadwal')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('info')
                ->url(fn () => JadwalRuang::getUrl())
                ->visible(fn () => JadwalRuang::canAccess()),

            CreateAction::make()
                ->label('Buat Reservasi')
                ->icon('heroicon-s-plus')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        $model = static::getResource()::getModel();

        // Catatan: closure yang diberikan ke Filament wajib memakai nama parameter $query.
        // Filament mengisi parameter berdasarkan nama; nama lain membuat Builder kosong.
        $menunggu = fn (Builder $q) => $q
            ->where('status', BookingStatus::Diajukan)
            ->where('end_at', '>=', now());

        $akanDatang = fn (Builder $q) => $q
            ->where('status', BookingStatus::Disetujui)
            ->where('end_at', '>=', now());

        return [
            'menunggu' => Tab::make('Menunggu Persetujuan')
                ->modifyQueryUsing(fn (Builder $query) => $menunggu($query))
                ->badge(fn () => $menunggu($model::query())->count())
                ->badgeColor('warning'),

            'akan_datang' => Tab::make('Akan Datang')
                ->modifyQueryUsing(fn (Builder $query) => $akanDatang($query)->reorder('start_at'))
                ->badge(fn () => $akanDatang($model::query())->count())
                ->badgeColor('success'),

            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', BookingStatus::Disetujui)
                    ->where('end_at', '<', now())),

            'ditolak' => Tab::make('Ditolak & Batal')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    BookingStatus::Ditolak->value,
                    BookingStatus::Batal->value,
                ])),

            'semua' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'akan_datang';
    }
}