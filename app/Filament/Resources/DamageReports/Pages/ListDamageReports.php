<?php

namespace App\Filament\Resources\DamageReports\Pages;

use App\Enums\DamageSeverity;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\Asset;
use App\Models\DamageReport;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListDamageReports extends ListRecords
{
    protected static string $resource = DamageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lapor')
                ->label('Laporkan Kerusakan')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->modalWidth('2xl')
                ->modalHeading('Laporkan Kerusakan Aset')
                ->modalSubmitActionLabel('Kirim Laporan')
                ->schema([
                    Select::make('asset_id')
                        ->label('Aset')
                        ->options(fn () => Asset::query()
                            ->active()
                            ->get()
                            ->mapWithKeys(fn (Asset $a) => [
                                $a->id => $a->code . ' — ' . $a->name
                                    . ($a->room ? ' (' . $a->room->name . ')' : ''),
                            ]))
                        ->required()
                        ->searchable()
                        ->native(false),

                    Select::make('severity')
                        ->label('Tingkat Kerusakan')
                        ->options(DamageSeverity::class)
                        ->default(DamageSeverity::Sedang)
                        ->required()
                        ->native(false),

                    Textarea::make('description')
                        ->label('Keterangan Kerusakan')
                        ->placeholder('Jelaskan kerusakan yang terjadi')
                        ->rows(4)
                        ->minLength(10)
                        ->required(),
                ])
                ->action(function (array $data) {
                    // reported_by dan reporter_name diisi otomatis oleh booted() di model
                    DamageReport::create([
                        'asset_id' => $data['asset_id'],
                        'severity' => $data['severity'],
                        'description' => $data['description'],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Laporan kerusakan terkirim')
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'belum' => Tab::make('Belum Ditindak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_processed', false))
                ->badge(fn () => static::getResource()::getModel()::where('is_processed', false)->count())
                ->badgeColor('danger'),

            'sudah' => Tab::make('Sudah Ditindak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_processed', true)),

            'semua' => Tab::make('Semua'),
        ];
    }
}