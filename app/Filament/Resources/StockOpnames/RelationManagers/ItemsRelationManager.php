<?php

namespace App\Filament\Resources\StockOpnames\RelationManagers;

use App\Enums\AssetCondition;
use App\Enums\OpnameItemStatus;
use App\Enums\OpnameStatus;
use App\Enums\QuantityFollowUp;
use App\Models\Room;
use App\Models\StockOpnameItem;
use App\Services\OpnameService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Daftar Periksa';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'asset:id,code,name,condition,quantity',
                'expectedRoom:id,name',
                'foundRoom:id,name',
                'checker:id,name',
            ]))
            ->columns([
                TextColumn::make('kode_aset')
                    ->label('Aset')
                    ->state(fn (StockOpnameItem $record) => $record->asset?->code ?? 'Tidak tercatat')
                    ->description(fn (StockOpnameItem $record) => $record->asset?->name ?? $record->unregistered_name)
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('asset', fn ($q) => $q
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"))
                        ->orWhere('unregistered_name', 'like', "%{$search}%"))
                    ->weight('medium'),

                TextColumn::make('expectedRoom.name')
                    ->label('Lokasi Tercatat')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('foundRoom.name')
                    ->label('Lokasi Ditemukan')
                    ->placeholder('—'),

                TextColumn::make('asset.condition')
                    ->label('Kondisi Tercatat')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('condition_found')
                    ->label('Kondisi Ditemukan')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('quantity_label')
                    ->label('Jumlah')
                    ->placeholder('—')
                    ->color(fn (StockOpnameItem $record) => $record->hasQuantityDifference() ? 'warning' : null)
                    ->description(fn (StockOpnameItem $record) => $record->hasQuantityDifference()
                        ? ($record->quantity_follow_up?->getLabel() ?? 'Tindak lanjut belum dipilih')
                        : null),

                TextColumn::make('status')
                    ->label('Hasil dan Petugas')
                    ->badge()
                    ->description(fn (StockOpnameItem $record) => $record->checked_at
                        ? $record->checked_at->format('d/m/Y H:i').' - '.($record->checker?->name ?? '—')
                        : 'Belum diperiksa')
                    // Urutkan menurut waktu periksa, lebih berguna daripada urutan nama status
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('checked_at', $direction)),

                // TextColumn::make('checker.name')->label('Petugas')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Hasil')->options(OpnameItemStatus::class),

                Filter::make('belum')
                    ->label('Belum Diperiksa')
                    ->query(fn (Builder $query) => $query->whereNull('checked_at')),

                Filter::make('selisih')
                    ->label('Ada Selisih')
                    ->query(fn (Builder $query) => $query->discrepancy()),
            ])
            ->defaultSort('status')
            ->recordActions([
                Action::make('periksa')
                    ->label('Catat')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('primary')
                    ->modalWidth('lg')
                    ->modalHeading(fn (StockOpnameItem $record) => 'Periksa: '.$record->display_name)
                    ->fillForm(fn (StockOpnameItem $record) => [
                        'found_room_id' => $record->found_room_id ?? $record->expected_room_id,
                        'condition' => ($record->condition_found ?? $record->asset?->condition)?->value,
                        'found_quantity' => $record->found_quantity
                            ?? $record->expected_quantity
                            ?? $record->asset?->quantity,
                        'note' => $record->note,
                    ])
                    ->schema([
                        Select::make('found_room_id')
                            ->label('Ruang Tempat Ditemukan')
                            ->options(fn () => Room::query()
                                ->with('building')
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn (Room $r) => [$r->id => $r->full_name]))
                            ->searchable()
                            ->native(false)
                            ->helperText('Kosongkan bila barang tidak ditemukan.'),

                        Select::make('condition')
                            ->label('Kondisi Fisik')
                            ->options(AssetCondition::class)
                            ->native(false),

                        // Aset kolektif: hitung unitnya
                        TextInput::make('found_quantity')
                            ->label('Jumlah Ditemukan')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->suffix('unit')
                            ->required(fn (?StockOpnameItem $record) => (bool) $record?->asset?->isCollective())
                            ->visible(fn (?StockOpnameItem $record) => (bool) $record?->asset?->isCollective())
                            ->helperText(function (?StockOpnameItem $record) {
                                $asset = $record?->asset;

                                if (! $asset) {
                                    return null;
                                }

                                $dipinjam = OpnameService::unitsOnLoan($asset);

                                return 'Tercatat '.($record->expected_quantity ?? $asset->quantity).' unit.'
                                    .($dipinjam ? ' Sedang dipinjam '.$dipinjam.' unit, ikut hitung sebagai ditemukan.' : '');
                            }),

                        Textarea::make('note')->label('Catatan')->rows(2),
                    ])
                    ->visible(fn () => $this->getOwnerRecord()->status === OpnameStatus::Berjalan)
                    ->action(function (StockOpnameItem $record, array $data) {
                        $record->recordScan(
                            $data['found_room_id'] ?? null,
                            OpnameService::toCondition($data['condition'] ?? null),
                            $data['note'] ?? null,
                            isset($data['found_quantity']) ? (int) $data['found_quantity'] : null,
                        );

                        Notification::make()
                            ->success()
                            ->title('Hasil dicatat')
                            ->body($record->fresh()->status->getLabel())
                            ->send();
                    }),

                Action::make('tidak_ditemukan')
                    ->label('Tidak Ada')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Tidak Ditemukan')
                    ->schema([
                        Textarea::make('note')->label('Keterangan')->rows(2),
                    ])
                    ->visible(fn () => $this->getOwnerRecord()->status === OpnameStatus::Berjalan)
                    ->action(function (StockOpnameItem $record, array $data) {
                        $record->markNotFound($data['note'] ?? null);

                        Notification::make()->warning()->title('Ditandai tidak ditemukan')->send();
                    }),

                Action::make('tindak_lanjut')
                    ->label('Tindak Lanjut')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('warning')
                    ->modalHeading(fn (StockOpnameItem $record) => 'Selisih Jumlah: '.$record->display_name)
                    ->modalDescription(fn (StockOpnameItem $record) => 'Ditemukan '.$record->found_quantity
                        .' dari '.$record->expected_quantity.' unit. Tindak lanjut dijalankan saat opname ditutup dengan opsi terapkan perubahan.')
                    ->fillForm(fn (StockOpnameItem $record) => [
                        'quantity_follow_up' => $record->quantity_follow_up?->value,
                    ])
                    ->schema([
                        Radio::make('quantity_follow_up')
                            ->label('Pilih tindak lanjut')
                            ->options(fn (?StockOpnameItem $record) => collect(QuantityFollowUp::cases())
                                // Laporan kehilangan hanya untuk kekurangan
                                ->reject(fn ($c) => $c === QuantityFollowUp::Laporkan && ($record?->quantityDifference() ?? 0) > 0)
                                ->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])
                                ->all())
                            ->descriptions(collect(QuantityFollowUp::cases())
                                ->mapWithKeys(fn ($c) => [$c->value => $c->getDescription()])
                                ->all())
                            ->required(),
                    ])
                    ->visible(fn (StockOpnameItem $record) => $record->hasQuantityDifference()
                        && $this->getOwnerRecord()->status === OpnameStatus::Berjalan)
                    ->action(function (StockOpnameItem $record, array $data) {
                        $record->forceFill(['quantity_follow_up' => $data['quantity_follow_up']])->save();

                        Notification::make()->success()->title('Tindak lanjut disimpan')->send();
                    }),
            ])
            ->emptyStateHeading('Daftar periksa kosong')
            ->emptyStateDescription('Klik "Mulai Opname" untuk membangkitkan daftar aset yang harus diperiksa.');
    }
}
