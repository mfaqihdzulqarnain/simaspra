<?php

namespace App\Filament\Resources\Loans\Tables;

use App\Enums\BorrowerType;
use App\Enums\LoanPurpose;
use App\Enums\LoanStatus;
use App\Filament\Actions\SendReminderAction;
use App\Models\Loan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user:id,name',
                'laboratory:id,name',
                'supervisor:id,name',
            ]))
            ->columns([
                TextColumn::make('loan_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('borrower_name')
                    ->label('Peminjam')
                    ->searchable(['borrower_name', 'borrower_identity'])
                    ->description(fn (Loan $record) => $record->borrower_identity
                        ?? $record->borrower_institution
                        ?? $record->borrower_type->getLabel()),

                TextColumn::make('laboratory.name')
                    ->label('Lab')
                    ->placeholder('Umum')
                    ->toggleable(),

                TextColumn::make('purpose_type')
                    ->label('Keperluan')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                // Dalam unit: aset kolektif dihitung sesuai jumlahnya
                TextColumn::make('items_sum_quantity')
                    ->label('Alat')
                    ->sum('items', 'quantity')
                    ->default(0)
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('start_at')
                    ->label('Mulai')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('end_at')
                    ->label('Batas Kembali')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->badge()
                    ->color(fn (Loan $record) => match (true) {
                        $record->status === LoanStatus::Dikembalikan => 'success',
                        $record->is_overdue => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Loan $record) => $record->late_label
                        ? 'Terlambat '.$record->late_label
                        : null),

                TextColumn::make('reminders_count')
                    ->label('Diingatkan')
                    ->counts('reminders')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? $state.'×' : '—')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('status')->label('Status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(LoanStatus::class),

                SelectFilter::make('laboratory_id')
                    ->label('Laboratorium')
                    ->relationship('laboratory', 'name')
                    ->preload(),

                SelectFilter::make('purpose_type')->label('Keperluan')->options(LoanPurpose::class),

                SelectFilter::make('borrower_type')->label('Jenis Peminjam')->options(BorrowerType::class),

                Filter::make('berjalan')
                    ->label('Sedang Dipinjam')
                    ->query(fn (Builder $query) => $query->ongoing()),

                Filter::make('terlambat')
                    ->label('Terlambat')
                    ->query(fn (Builder $query) => $query->whereIn('status', [
                        LoanStatus::Dipinjam->value,
                        LoanStatus::Terlambat->value,
                    ])->where('end_at', '<', now())),

                Filter::make('jatuh_tempo')
                    ->label('Jatuh Tempo 24 Jam')
                    ->query(fn (Builder $query) => $query->dueSoon(24)),
            ])
            ->defaultSort('start_at', 'desc')
            ->recordActions([
                SendReminderAction::make(),
                ViewAction::make(),
                EditAction::make()->label('')
                    ->visible(fn (Loan $record) => $record->status === LoanStatus::Diajukan),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada peminjaman')
            ->emptyStateDescription('Ajukan peminjaman alat laboratorium atau aset kampus.');
    }
}
