<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Laboratory;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Services\LabelService;
use App\Services\MutationService;
use App\Support\Sapra;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'category:id,name',
                'unit:id,name',
                'room:id,name,building_id,laboratory_id',
                'room.building:id,code',
                'room.laboratory:id,code',
                'media',
            ]))
            ->columns([
                ImageColumn::make('foto')
                    ->label('')
                    ->getStateUsing(fn (Asset $record) => $record->getFirstMediaUrl('foto', 'thumb') ?: null)
                    ->square()
                    ->imageHeight(40)
                    ->defaultImageUrl(url('/images/asset-placeholder.png'))
                    ->toggleable(),

                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable()
                    ->description(fn (Asset $record) => $record->category?->name),

                TextColumn::make('name')
                    ->label('Nama Aset')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Asset $record) => trim(($record->brand ?? '').' '.($record->model ?? '')) ?: null),

                TextColumn::make('quantity')
                    ->label('Jml')
                    ->alignCenter()
                    ->formatStateUsing(fn (int $state) => $state > 1 ? number_format($state, 0, ',', '.') : '1')
                    ->color(fn (int $state) => $state > 1 ? 'primary' : 'gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('unit.name')
                    ->label('Lokasi Unit')
                    ->sortable()
                    ->toggleable()
                    ->description(fn (Asset $record) => $record->room?->name),

                TextColumn::make('room.laboratory.code')
                    ->label('Posisi Lab')
                    ->placeholder('Aset Umum')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('kondisi_status')
                    ->label('Kondisi')
                    ->getStateUsing(fn (Asset $record) => array_values(array_filter([
                        $record->condition,
                        $record->status === AssetStatus::Aktif ? null : $record->status,
                    ])))
                    ->badge()
                    ->listWithLineBreaks()
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('condition', $direction)
                        ->orderBy('status', $direction)),

                TextColumn::make('year_acquired')->label('Tahun')->alignCenter()->sortable()->toggleable(),

                TextColumn::make('acquisition_value')
                    ->label('Harga Unit')
                    ->formatStateUsing(fn ($state) => Sapra::rupiahRingkas($state))
                    ->tooltip(fn ($state) => Sapra::rupiah($state))
                    // ->alignEnd()
                    ->sortable(),

                IconColumn::make('is_loanable')
                    ->label('Bisa Pinjam')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (Asset $record) => $record->is_loanable
                        ? 'Boleh dipinjam: '.$record->loanable_by?->getLabel()
                        : 'Tidak dipinjamkan'),

                TextColumn::make('label_printed_at')
                    ->label('Label')
                    ->badge()
                    ->getStateUsing(fn (Asset $record) => $record->label_printed_at ? 'Sudah' : 'Belum')
                    ->color(fn (Asset $record) => $record->label_printed_at ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(AssetStatus::class),
                SelectFilter::make('condition')->label('Kondisi')->options(AssetCondition::class),

                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('asset_category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('laboratory')
                    ->label('Posisi Lab')
                    ->options(fn () => Laboratory::query()
                        ->orderBy('code')
                        ->pluck('code', 'id')
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $labId) => $q->inLaboratory((int) $labId),
                    )),

                Filter::make('unlabeled')
                    ->label('Belum Berlabel')
                    ->query(fn (Builder $query) => $query->whereNull('label_printed_at')),

                Filter::make('loanable')
                    ->label('Dapat Dipinjam')
                    ->query(fn (Builder $query) => $query->where('is_loanable', true)),
            ])
            ->defaultSort('code')
            ->recordActions([
                Action::make('cetak_label')
                    ->label('')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->url(fn (Asset $record) => route('label.single', $record))
                    ->openUrlInNewTab(),
                ViewAction::make()
                    ->color('primary')
                    ->label('')
                    ->icon(Heroicon::OutlinedEye),
                EditAction::make()->label('')
                    ->color('warning')
                    ->label('')
                    ->icon(Heroicon::OutlinedPencilSquare),
                Action::make('mutasi')
                    ->label('Mutasi')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('danger')
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Asset $record) => 'Mutasi Aset: '.$record->code)
                    ->modalDescription('Catat perpindahan aset ke ruang atau unit lain.')
                    ->modalSubmitActionLabel('Catat Mutasi')
                    ->visible(fn (Asset $record) => MutationService::isMovable($record)
                        && Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                    ->schema([
                        Section::make('Posisi Sekarang')
                            ->columns(2)
                            ->schema([
                                Placeholder::make('unit_lama')
                                    ->label('Unit')
                                    ->content(fn (Asset $record) => $record->unit?->name ?? '—'),

                                Placeholder::make('ruang_lama')
                                    ->label('Ruang')
                                    ->content(fn (Asset $record) => $record->room?->full_name ?? '—'),
                            ]),

                        Section::make('Posisi Baru')
                            ->columns(2)
                            ->schema([
                                Select::make('to_unit_id')
                                    ->label('Unit Tujuan')
                                    ->options(fn () => Unit::where('is_active', true)->pluck('name', 'id'))
                                    ->default(fn (Asset $record) => $record->unit_id)
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Kosongkan bila unit tidak berubah.'),

                                Select::make('to_room_id')
                                    ->label('Ruang Tujuan')
                                    ->options(fn () => Room::query()
                                        ->with('building')
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn (Room $r) => [$r->id => $r->full_name]))
                                    ->searchable()
                                    ->native(false),

                                DatePicker::make('moved_at')
                                    ->label('Tanggal Mutasi')
                                    ->default(now())
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),

                                Select::make('to_user_id')
                                    ->label('Diserahkan Kepada')
                                    ->options(fn () => User::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Penanggung jawab di lokasi baru.'),

                                Textarea::make('reason')
                                    ->label('Alasan Mutasi')
                                    ->placeholder('Contoh: Kebutuhan praktikum semester genap')
                                    ->rows(3)
                                    ->required()
                                    ->columnSpanFull(),

                                Toggle::make('create_handover')
                                    ->label('Buat Berita Acara Mutasi')
                                    ->default(true)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function (Asset $record, array $data) {
                        $handover = MutationService::move(
                            Asset::whereKey($record->getKey())->get(),
                            $data['to_unit_id'] ?? null,
                            $data['to_room_id'] ?? null,
                            $data['reason'],
                            $data['moved_at'],
                            $data['to_user_id'] ?? null,
                            $data['create_handover'] ?? true,
                        );

                        Notification::make()
                            ->success()
                            ->title('Mutasi tercatat')
                            ->body($handover
                                ? 'Berita acara '.$handover->number.' dibuat.'
                                : 'Posisi aset diperbarui.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('cetak_label_sheet')
                        ->label('Cetak Massal — Sheet')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->color('primary')
                        ->action(function (Collection $records) {
                            $pdf = LabelService::sheet($records);

                            return Response::streamDownload(
                                fn () => print ($pdf->output()),
                                LabelService::filename($records),
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('cetak_label_thermal')
                        ->label('Cetak Satuan — Thermal')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->color('gray')
                        ->action(function (Collection $records) {
                            $pdf = LabelService::thermal($records);

                            return Response::streamDownload(
                                fn () => print ($pdf->output()),
                                LabelService::filename($records),
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mutasi_massal')
                        ->label('Mutasi Aset Terpilih')
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->color('warning')
                        ->modalWidth('2xl')
                        ->modalHeading('Mutasi Massal')
                        ->modalDescription('Seluruh aset terpilih dipindahkan ke lokasi yang sama.')
                        ->modalSubmitActionLabel('Catat Mutasi')
                        ->visible(fn () => Auth::user()?->hasAnyRole(['kabag_sarpras', 'super_admin']))
                        ->schema([
                            Select::make('to_unit_id')
                                ->label('Unit Tujuan')
                                ->options(fn () => Unit::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->native(false)
                                ->helperText('Kosongkan bila unit tidak berubah.'),

                            Select::make('to_room_id')
                                ->label('Ruang Tujuan')
                                ->options(fn () => Room::query()
                                    ->with('building')
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn (Room $r) => [$r->id => $r->full_name]))
                                ->searchable()
                                ->native(false),

                            DatePicker::make('moved_at')
                                ->label('Tanggal Mutasi')
                                ->default(now())
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y'),

                            Select::make('to_user_id')
                                ->label('Diserahkan Kepada')
                                ->options(fn () => User::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->native(false),

                            Textarea::make('reason')
                                ->label('Alasan Mutasi')
                                ->rows(3)
                                ->required(),

                            Toggle::make('create_handover')
                                ->label('Buat Berita Acara Mutasi')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $movable = $records->filter(fn (Asset $a) => MutationService::isMovable($a));

                            if ($movable->isEmpty()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Tidak ada aset yang dapat dimutasi')
                                    ->body('Aset yang sedang dipinjam atau diusulkan hapus tidak bisa dipindahkan.')
                                    ->send();

                                return;
                            }

                            $handover = MutationService::move(
                                $movable,
                                $data['to_unit_id'] ?? null,
                                $data['to_room_id'] ?? null,
                                $data['reason'],
                                $data['moved_at'],
                                $data['to_user_id'] ?? null,
                                $data['create_handover'] ?? true,
                            );

                            $dilewati = $records->count() - $movable->count();

                            Notification::make()
                                ->success()
                                ->title($movable->count().' aset dimutasi')
                                ->body(collect([
                                    $handover ? 'Berita acara '.$handover->number.' dibuat.' : null,
                                    $dilewati > 0 ? $dilewati.' aset dilewati karena statusnya.' : null,
                                ])->filter()->implode(' '))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada aset')
            ->emptyStateDescription('Tambahkan aset, kode dan QR dibuat otomatis.');
    }
}
