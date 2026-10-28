<?php

namespace App\Filament\Resources\DamageReports\Tables;

use App\Enums\DamageSeverity;
use App\Enums\PriorityLevel;
use App\Models\DamageReport;
use App\Models\User;
use App\Models\Vendor;
use App\Services\MaintenanceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DamageReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'asset:id,code,name,unit_id',
                'asset.unit:id,name',
                'reporter:id,name',
                'consumable:id,name,unit_of_measure,laboratory_id',
                'loan:id,loan_number',
            ]))
            ->columns([
                TextColumn::make('reported_at')
                    ->label('Waktu Lapor')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('barang')
                    ->label('Barang')
                    ->state(fn (DamageReport $record) => $record->asset?->code
                        ?? ($record->consumable ? 'Alat massal' : '—'))
                    ->description(fn (DamageReport $record) => ($record->asset?->name ?? $record->consumable?->name)
                        .($record->quantity ? ' × '.$record->quantity : '')
                        .($record->loan ? ' · '.$record->loan->loan_number : ''))
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('asset', fn ($q) => $q
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"))
                        ->orWhereHas('consumable', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->weight('medium'),

                TextColumn::make('asset.unit.name')
                    ->label('Unit')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('severity')->label('Tingkat')->badge()->sortable(),

                TextColumn::make('description')->label('Keterangan')->wrap(),

                TextColumn::make('reporter_label')
                    ->label('Pelapor')
                    ->description(fn (DamageReport $record) => $record->is_public_report
                        ? 'via scan QR'
                        : 'via panel'),

                IconColumn::make('is_processed')
                    ->label('Ditindak')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('severity')->label('Tingkat')->options(DamageSeverity::class),

                Filter::make('belum_ditindak')
                    ->label('Belum Ditindaklanjuti')
                    ->query(fn (Builder $query) => $query->where('is_processed', false))
                    ->default(),

                SelectFilter::make('asset')
                    ->label('Unit')
                    ->relationship('asset.unit', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('reported_at', 'desc')
            ->recordActions([
                Action::make('buat_wo')
                    ->label('Buat Perintah Kerja')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->color('primary')
                    ->modalWidth('2xl')
                    ->modalHeading('Tindak Lanjut Laporan')
                    ->modalSubmitActionLabel('Buat Perintah Kerja')
                    // Perintah kerja hanya untuk aset ber-kode; alat massal cukup ditandai selesai
                    ->visible(fn (DamageReport $record) => ! $record->is_processed
                        && $record->asset_id !== null
                        && Auth::user()?->hasAnyRole(['kabag_sarpras', 'teknisi', 'super_admin']))
                    ->fillForm(fn (DamageReport $record) => [
                        'description' => $record->description,
                        'priority' => $record->severity->toPriority()->value,
                    ])
                    ->schema([
                        Section::make()
                            ->columns(2)
                            ->schema([
                                Select::make('priority')
                                    ->label('Prioritas')
                                    ->options(PriorityLevel::class)
                                    ->required()
                                    ->native(false),

                                DatePicker::make('scheduled_at')
                                    ->label('Rencana Pengerjaan')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()),

                                Select::make('assigned_to')
                                    ->label('Teknisi')
                                    ->options(fn () => User::query()
                                        ->whereHas('roles', fn ($q) => $q->whereIn('name', ['teknisi', 'kabag_sarpras']))
                                        ->where('is_active', true)
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Kosongkan bila belum ditentukan.'),

                                Select::make('vendor_id')
                                    ->label('Vendor (bila diperbaiki pihak luar)')
                                    ->options(fn () => Vendor::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false),

                                Textarea::make('description')
                                    ->label('Uraian Pekerjaan')
                                    ->rows(3)
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (DamageReport $record, array $data) {
                        $order = MaintenanceService::createFromReport($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Perintah kerja dibuat')
                            ->body('Nomor: '.$order->number)
                            ->send();
                    }),

                Action::make('abaikan')
                    ->label('Tandai Selesai')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Laporan Selesai')
                    ->modalDescription('Gunakan bila laporan tidak perlu perbaikan, misalnya salah lapor atau sudah teratasi sendiri.')
                    ->visible(fn (DamageReport $record) => ! $record->is_processed
                        && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                    ->action(function (DamageReport $record) {
                        $record->forceFill(['is_processed' => true])->save();

                        Notification::make()->success()->title('Laporan ditandai selesai')->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada laporan kerusakan')
            ->emptyStateDescription('Laporan masuk otomatis saat pengguna memindai QR aset dan mengisi form.');
    }
}
