<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\Loan;
use App\Models\User;
// use Filament\Notifications\Actions\Action;
use Filament\Actions\Action;
use App\Models\Laboratory;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class NotifyService
{
    /** Kirim ke semua pengguna aktif dengan peran tertentu */
    public static function toRoles(
        array|string $roles,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $color = 'info',
        string $icon = 'heroicon-o-bell',
    ): void {
        $users = User::query()
            ->role($roles)
            ->where('is_active', true)
            ->get();

        static::send($users, $title, $body, $url, $color, $icon);
    }

    /** Kirim ke satu pengguna */
    public static function toUser(
        ?int $userId,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $color = 'info',
        string $icon = 'heroicon-o-bell',
    ): void {
        if (! $userId) {
            return;
        }

        $user = User::query()->whereKey($userId)->where('is_active', true)->first();

        if (! $user) {
            return;
        }

        static::send(new Collection([$user]), $title, $body, $url, $color, $icon);
    }

    protected static function send(
        Collection $users,
        string $title,
        ?string $body,
        ?string $url,
        string $color,
        string $icon,
    ): void {
        if ($users->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->icon($icon)
            ->color($color);

        if ($body) {
            $notification->body($body);
        }

        if ($url) {
            $notification->actions([
                Action::make('lihat')
                    ->label('Lihat')
                    ->url($url)
                    ->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($users);
    }

    public static function markOverdue(): int
    {
        $loans = Loan::query()->overdue()->get();

        foreach ($loans as $loan) {
            $loan->forceFill(['status' => LoanStatus::Terlambat])->save();

            NotifyService::toUser(
                $loan->user_id,
                'Peminjaman terlambat',
                $loan->loan_number.' — jatuh tempo '.$loan->end_date->format('d/m/Y'),
                LoanResource::getUrl('view', ['record' => $loan]),
                'danger',
                'heroicon-o-clock',
            );
        }

        if ($loans->isNotEmpty()) {
            NotifyService::toRoles(
                ['kabag_sarpras'],
                $loans->count().' peminjaman terlambat',
                'Perlu ditagih pengembaliannya.',
                LoanResource::getUrl('index'),
                'danger',
                'heroicon-o-clock',
            );
        }

        return $loans->count();
    }

        /**
     * Kirim ke laboran yang bertugas di lab tertentu.
     * Bila lab tidak ditentukan atau belum punya laboran,
     * notifikasi jatuh ke Ka. Sarpras agar tidak hilang.
     */
    public static function toLabStaff(
        ?int $laboratoryId,
        string $title,
        ?string $body = null,
        ?string $url = null,
        string $color = 'info',
        string $icon = 'heroicon-o-beaker',
    ): void {
        $users = $laboratoryId
            ? Laboratory::find($laboratoryId)?->staff()->where('is_active', true)->get()
            : null;

        if (! $users || $users->isEmpty()) {
            static::toRoles(['kabag_sarpras'], $title, $body, $url, $color, $icon);

            return;
        }

        static::send($users, $title, $body, $url, $color, $icon);
    }
}
