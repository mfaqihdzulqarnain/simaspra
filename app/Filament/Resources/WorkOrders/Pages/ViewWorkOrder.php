<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Enums\AssetCondition;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MaintenanceService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            /* -------------------- Tugaskan -------------------- */
            Action::make('tugaskan')
                ->label('Tugaskan')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('primary')
                ->schema([
                    Select::make('assigned_to')
                        ->label('Teknisi')
                        ->options(fn () => User::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['teknisi', 'kabag_sarpras']))
                            ->where('is_active', true)
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->native(false),

                    DatePicker::make('scheduled_at')
                        ->label('Rencana Pengerjaan')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                // Laporan baru ditugaskan; yang sudah ditugaskan boleh dialihkan
                ->visible(fn (WorkOrder $record) => in_array($record->status, [
                        WorkOrderStatus::Dilaporkan,
                        WorkOrderStatus::Ditugaskan,
                    ], true)
                        && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                ->action(function (WorkOrder $record, array $data) {
                    MaintenanceService::assign(
                        $record,
                        $data['assigned_to'],
                        null,
                        $data['scheduled_at'] ?? null,
                    );

                    Notification::make()->success()->title('Pekerjaan ditugaskan')->send();
                }),

            /* ------------------ Mulai kerjakan ------------------ */
            Action::make('mulai')
                ->label('Mulai Kerjakan')
                ->icon(Heroicon::OutlinedPlayCircle)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Mulai Pengerjaan')
                ->modalDescription('Status aset akan berubah menjadi Dalam Perbaikan dan tidak dapat dipinjam.')
                ->visible(fn (WorkOrder $record) => $record->status === WorkOrderStatus::Ditugaskan
                    && ($record->assigned_to === Auth::id()
                        || Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin'])))
                ->action(function (WorkOrder $record) {
                    try {
                        MaintenanceService::start($record);
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title('Belum bisa dimulai')->body($e->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Pengerjaan dimulai')->send();
                }),

            /* -------------------- Selesai -------------------- */
            Action::make('selesai')
                ->label('Selesaikan')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->modalWidth('2xl')
                ->schema([
                    Select::make('condition_after')
                        ->label('Kondisi Aset Setelah Perbaikan')
                        ->options(AssetCondition::class)
                        ->default(AssetCondition::Baik)
                        ->required(fn () => ! $this->getRecord()->asset?->isCollective())
                        ->visible(fn () => ! $this->getRecord()->asset?->isCollective())
                        ->native(false),

                    TextInput::make('cost')
                        ->label('Biaya Perbaikan')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->prefix('Rp')
                        ->required(),

                    Textarea::make('result_note')
                        ->label('Hasil Pekerjaan')
                        ->rows(3)
                        ->required(),
                ])
                ->visible(fn (WorkOrder $record) => $record->status === WorkOrderStatus::Dikerjakan
                    && filled($record->assigned_to)
                    && ($record->assigned_to === Auth::id()
                        || Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin'])))
                ->action(function (WorkOrder $record, array $data) {
                    MaintenanceService::finish($record, $data);

                    $notification = Notification::make()->success()->title('Pekerjaan selesai');

                    if ($record->fresh()->exceedsRepairThreshold()) {
                        $notification->body('Perhatian: total biaya perbaikan aset ini sudah melampaui 50% nilai perolehan. Pertimbangkan penghapusan.')
                            ->persistent();
                    }

                    $notification->send();
                }),

            /* ---------------- Tidak dapat diperbaiki ---------------- */
            Action::make('gagal')
                ->label('Tidak Dapat Diperbaiki')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->modalHeading('Tandai Tidak Dapat Diperbaiki')
                ->modalDescription(fn (WorkOrder $record) => $record->asset?->isCollective()
                ? 'Aset kolektif: status dan kondisinya tidak diubah. Unit yang rusak atau tidak ditemukan dikurangkan dari jumlah aset.'
                : 'Aset akan ditandai Rusak Berat dan diusulkan untuk penghapusan.')
                    ->schema([
                        Textarea::make('note')
                            ->label('Alasan')
                            ->rows(3)
                            ->required(),

                        TextInput::make('units_lost')
                            ->label('Kurangi Jumlah Aset')
                            ->helperText(fn () => 'Unit yang rusak total atau tidak ditemukan. Tercatat '
                                .($this->getRecord()->asset?->quantity ?? 0).' unit; nilai perolehan ikut berkurang sebanding.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(fn () => (int) ($this->getRecord()->asset?->quantity ?? 0))
                            ->default(0)
                            ->suffix('unit')
                            ->visible(fn () => (bool) $this->getRecord()->asset?->isCollective()),

                        TextInput::make('cost')
                            ->label('Biaya yang Sudah Dikeluarkan')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('Rp'),
                    ])
                ->visible(fn (WorkOrder $record) => $record->isOpen()
                    && Auth::user()?->hasAnyRole(['teknisi', 'kabag_sarpras', 'super_admin']))
                ->action(function (WorkOrder $record, array $data) {
                    $kolektif = (bool) $record->asset?->isCollective();
                    $hilang = (int) ($data['units_lost'] ?? 0);

                    MaintenanceService::fail($record, $data['note'], (float) ($data['cost'] ?? 0), $hilang);

                    Notification::make()
                        ->warning()
                        ->title('Ditandai tidak dapat diperbaiki')
                        ->body(match (true) {
                            ! $kolektif => 'Aset kini berstatus Diusulkan Dihapus.',
                            $hilang > 0 => 'Jumlah aset dikurangi '.$hilang.' unit.',
                            default => 'Jumlah aset tidak diubah.',
                        })
                        ->send();
                }),

            EditAction::make()->label('Ubah')
                ->visible(fn (WorkOrder $record) => $record->isOpen()),

            Action::make('kembali')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Section::make('Perintah Kerja')
                    ->columnSpan(3)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Nomor')->weight('bold'),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('type')->label('Jenis')->badge(),
                        TextEntry::make('priority')->label('Prioritas')->badge(),
                        TextEntry::make('assignee.name')->label('Teknisi')->placeholder('Belum ditugaskan'),
                        TextEntry::make('vendor.name')->label('Vendor')->placeholder('—'),
                        TextEntry::make('scheduled_at')->label('Rencana')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('started_at')->label('Mulai')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('finished_at')->label('Selesai')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('cost')
                            ->label('Biaya')
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.')),
                        TextEntry::make('description')->label('Uraian Pekerjaan')->columnSpanFull(),
                        TextEntry::make('result_note')->label('Hasil')->placeholder('—')->columnSpanFull(),
                    ]),
            ]),

            Grid::make(2)->schema([
                Section::make('Aset')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('info_aset')
                            ->hiddenLabel()
                            ->state(function (WorkOrder $record) {
                                $asset = $record->asset;

                                if (! $asset) {
                                    return new HtmlString('<p style="font-size:13px;color:#667085">Aset tidak ditemukan.</p>');
                                }

                                $lifetime = $record->assetLifetimeCost();
                                $value = (float) $asset->acquisition_value;
                                $percent = $value > 0 ? round($lifetime / $value * 100, 1) : 0;

                                $warna = $percent >= 50 ? '#D92D20' : '#667085';

                                return new HtmlString(
                                    '<p style="margin:0;font-family:monospace;font-weight:600;font-size:14px">'
                                    .e($asset->code).'</p>'
                                    .'<p style="margin:4px 0 12px;font-size:13px">'.e($asset->name).'</p>'
                                    .'<p style="margin:0;font-size:12px;color:#667085">Lokasi</p>'
                                    .'<p style="margin:0 0 10px;font-size:13px">'.e($asset->room?->full_name ?? '—').'</p>'
                                    .'<p style="margin:0;font-size:12px;color:#667085">Nilai Perolehan</p>'
                                    .'<p style="margin:0 0 10px;font-size:13px">Rp '
                                    .number_format($value, 0, ',', '.').'</p>'
                                    .'<p style="margin:0;font-size:12px;color:#667085">Total Biaya Perbaikan</p>'
                                    .'<p style="margin:0;font-size:13px;color:'.$warna.'">Rp '
                                    .number_format($lifetime, 0, ',', '.')
                                    .' ('.$percent.'%)</p>'
                                );
                            })
                            ->html(),
                    ]),
            ]),
        ]);
    }
}
