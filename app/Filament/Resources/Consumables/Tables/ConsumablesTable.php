<?php

namespace App\Filament\Resources\Consumables\Tables;

use App\Enums\ConsumableType;
use App\Enums\StockMovementType;
use App\Models\Consumable;
use App\Models\ConsumableMovement;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ConsumablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'category:id,name',
                'storageRoom:id,name,building_id',
                'storageRoom.building:id,name',
                'unit:id,name',
                'laboratory:id,name',
            ]))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('medium'),

                TextColumn::make('type')->label('Jenis')->badge()->sortable(),

                TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Consumable $record) => $record->category?->name),

                TextColumn::make('unit.name')
                    ->label('Unit / Lab')
                    ->placeholder('—')
                    ->description(fn (Consumable $record) => $record->laboratory?->name)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('current_stock')
                    ->label('Stok')
                    ->formatStateUsing(function ($state, Consumable $record) {
                        $total = rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',');

                        if ($record->type === ConsumableType::AlatMassal && $record->on_loan > 0) {
                            return $total.' ('.$record->available_stock.' tersedia)';
                        }

                        return $total.' '.$record->unit_of_measure;
                    })
                    ->badge()
                    ->color(fn (Consumable $record) => $record->is_low ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('on_loan')
                    ->label('Dipinjam')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '—')
                    ->visible(fn () => true)
                    ->toggleable(),

                TextColumn::make('min_stock')
                    ->label('Minimum')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','))
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->toggleable(),

                TextColumn::make('stock_value')
                    ->label('Nilai Stok')
                    ->getStateUsing(fn (Consumable $record) => 'Rp '.number_format($record->stock_value, 0, ',', '.'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('storageRoom.name')->label('Penyimpanan')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')->label('Aktif')->boolean()->alignCenter(),
            ])
            ->filters([
                Filter::make('menipis')
                    ->label('Stok Menipis')
                    ->query(fn (Builder $query) => $query->lowStock()),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),

                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(ConsumableType::class),

                SelectFilter::make('unit_id')
                    ->label('Unit / Prodi')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => (bool) Auth::user()?->canSeeAllUnits()),

                SelectFilter::make('laboratory_id')
                    ->label('Laboratorium')
                    ->relationship('laboratory', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => (bool) Auth::user()?->canSeeAllUnits()),
            ])
            ->defaultSort('name')
            ->recordActions([
                /* -------------------- Barang masuk -------------------- */
                Action::make('masuk')
                    ->label('Masuk')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('success')
                    ->modalWidth('lg')
                    ->modalHeading(fn (Consumable $record) => 'Barang Masuk: '.$record->name)
                    ->visible(fn () => Auth::user()?->hasAnyRole(['kabag_sarpras', 'staf_ruang', 'laboran', 'super_admin']))
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->suffix(fn (Consumable $record) => $record->unit_of_measure),

                        DatePicker::make('moved_at')
                            ->label('Tanggal')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        TextInput::make('reference')
                            ->label('Referensi')
                            ->placeholder('Nomor faktur, nota, atau BAST')
                            ->maxLength(150),

                        Textarea::make('note')->label('Catatan')->rows(2),
                    ])
                    ->action(function (Consumable $record, array $data) {
                        ConsumableMovement::create([
                            'consumable_id' => $record->getKey(),
                            'type' => StockMovementType::Masuk,
                            'quantity' => $data['quantity'],
                            'moved_at' => $data['moved_at'],
                            'reference' => $data['reference'] ?? null,
                            'note' => $data['note'] ?? null,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Stok bertambah')
                            ->body('Sisa: '.$record->fresh()->current_stock.' '.$record->unit_of_measure)
                            ->send();
                    }),

                /* -------------------- Barang keluar -------------------- */
                Action::make('keluar')
                    ->label('Keluar')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('warning')
                    ->modalWidth('lg')
                    ->modalHeading(fn (Consumable $record) => 'Barang Keluar: '.$record->name)
                    ->modalDescription(fn (Consumable $record) => 'Stok tersedia: '
                        .$record->current_stock.' '.$record->unit_of_measure)
                    ->visible(fn (Consumable $record) => (float) $record->current_stock > 0
                        && $record->type === ConsumableType::HabisPakai
                        && Auth::user()?->hasAnyRole(['kabag_sarpras', 'staf_ruang', 'laboran', 'super_admin']))
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->suffix(fn (Consumable $record) => $record->unit_of_measure)
                            ->maxValue(fn (Consumable $record) => (float) $record->current_stock)
                            ->helperText('Tidak boleh melebihi stok tersedia.'),

                        Select::make('unit_id')
                            ->label('Unit Penerima')
                            ->options(fn () => Unit::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->native(false),

                        DatePicker::make('moved_at')
                            ->label('Tanggal')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Textarea::make('note')->label('Keperluan')->rows(2),
                    ])
                    ->action(function (Consumable $record, array $data) {
                        ConsumableMovement::create([
                            'consumable_id' => $record->getKey(),
                            'type' => StockMovementType::Keluar,
                            'quantity' => $data['quantity'],
                            'unit_id' => $data['unit_id'] ?? null,
                            'moved_at' => $data['moved_at'],
                            'note' => $data['note'] ?? null,
                        ]);

                        $sisa = $record->fresh();

                        $notif = Notification::make()
                            ->success()
                            ->title('Stok berkurang')
                            ->body('Sisa: '.$sisa->current_stock.' '.$record->unit_of_measure);

                        if ($sisa->is_low) {
                            $notif->warning()->title('Stok menipis')
                                ->body('Sisa '.$sisa->current_stock.' '.$record->unit_of_measure
                                    .', di bawah batas minimum.')
                                ->persistent();
                        }

                        $notif->send();
                    }),

                /* -------------------- Penyesuaian -------------------- */
                Action::make('penyesuaian')
                    ->label('')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('gray')
                    ->modalWidth('lg')
                    ->modalHeading(fn (Consumable $record) => 'Penyesuaian Stok: '.$record->name)
                    ->modalDescription('Gunakan setelah pemeriksaan fisik bila stok sistem tidak sesuai kenyataan.')
                    ->visible(fn () => Auth::user()?->hasAnyRole(['kabag_sarpras', 'laboran', 'super_admin']))
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Stok Fisik Sebenarnya')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->suffix(fn (Consumable $record) => $record->unit_of_measure)
                            ->default(fn (Consumable $record) => (float) $record->current_stock),

                        DatePicker::make('moved_at')
                            ->label('Tanggal')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Textarea::make('note')
                            ->label('Alasan Penyesuaian')
                            ->rows(2)
                            ->required(),
                    ])
                    ->action(function (Consumable $record, array $data) {
                        ConsumableMovement::create([
                            'consumable_id' => $record->getKey(),
                            'type' => StockMovementType::Penyesuaian,
                            'quantity' => $data['quantity'],
                            'moved_at' => $data['moved_at'],
                            'note' => $data['note'],
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Stok disesuaikan')
                            ->body('Stok kini: '.$record->fresh()->current_stock)
                            ->send();
                    }),

                EditAction::make()->label('')
                    ->label(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada barang habis pakai')
            ->emptyStateDescription('Catat persediaan seperti ATK, atau alat kebersihan.');
    }
}
