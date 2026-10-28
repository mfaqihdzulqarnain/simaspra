<?php

namespace App\Filament\Resources\RoomBookings\Tables;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\BorrowerType;
use App\Enums\PaymentStatus;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Services\RentalFeeService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'room:id,name,building_id',
                'room.building:id,name',
            ]))
            ->columns([
                TextColumn::make('booking_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (RoomBooking $record) => $record->booking_type?->getLabel()),

                TextColumn::make('title')
                    ->label('Kegiatan')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (RoomBooking $record) => $record->title)
                    ->description(fn (RoomBooking $record) => $record->borrower_name
                        .($record->borrower_institution ? ' · '.$record->borrower_institution : '')),

                TextColumn::make('room.name')
                    ->label('Ruang')
                    ->sortable()
                    ->description(fn (RoomBooking $record) => $record->room?->building?->name),

                TextColumn::make('start_at')
                    ->label('Jadwal')
                    ->sortable()
                    ->formatStateUsing(fn (RoomBooking $record) => $record->schedule_label),

                TextColumn::make('attendees')
                    ->label('Peserta')
                    ->alignCenter()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RoomBooking $record) => $record->status_label)
                    ->color(fn (RoomBooking $record) => $record->status_color),

                TextColumn::make('borrower_type')
                    ->label('Pemesan')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_status')
                    ->label('Sewa')
                    ->badge()
                    ->description(fn (RoomBooking $record) => $record->fee_amount > 0
                        ? RentalFeeService::rupiah($record->fee_amount)
                        : null)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('room_id')
                    ->label('Ruang')
                    ->options(fn () => Room::query()->bookable()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),

                SelectFilter::make('booking_type')
                    ->label('Jenis Kegiatan')
                    ->options(BookingType::class),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(BookingStatus::class),

                SelectFilter::make('borrower_type')
                    ->label('Pemesan')
                    ->options(BorrowerType::class),

                SelectFilter::make('payment_status')
                    ->label('Pembayaran Sewa')
                    ->options(PaymentStatus::class),

                Filter::make('tanggal')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal')->native(false),
                        DatePicker::make('sampai')->label('Sampai tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('end_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('start_at', '<=', $d)))
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['dari'] ?? null) && ! ($data['sampai'] ?? null)) {
                            return null;
                        }

                        return 'Tanggal: '.($data['dari'] ?? '…').' s.d. '.($data['sampai'] ?? '…');
                    }),
            ])
            ->defaultSort('start_at', 'desc')
            ->recordUrl(fn (RoomBooking $record) => \App\Filament\Resources\RoomBookings\RoomBookingResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->label(''),
            ])
            ->emptyStateHeading('Belum ada reservasi')
            ->emptyStateDescription('Klik "Buat Reservasi" untuk memesan ruang rapat, kelas, atau aula.');
    }
}
