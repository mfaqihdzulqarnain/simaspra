<?php

namespace App\Filament\Actions;

use App\Models\Loan;
use App\Services\ReminderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class SendReminderAction
{
    public static function make(string $nama = 'ingatkan'): Action
    {
        return Action::make($nama)
            ->label('Ingatkan via WA')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('danger')
            ->modalWidth('lg')
            ->modalHeading('Kirim Pengingat Keterlambatan')
            ->modalSubmitActionLabel('Buka WhatsApp')
            ->visible(fn (Loan $record) => ReminderService::needsReminder($record)
                && filled($record->borrower_phone)
                && Auth::user()?->hasAnyRole(['laboran', 'kabag_sarpras', 'super_admin']))
            ->fillForm(fn (Loan $record) => [
                'message' => ReminderService::compose($record),
            ])
            ->schema([
                Placeholder::make('tujuan')
                    ->label('Dikirim ke')
                    ->content(fn (Loan $record) => $record->borrower_name
                        . ' · ' . \App\Support\Phone::display($record->borrower_phone)),

                Textarea::make('message')
                    ->label('Isi Pesan')
                    ->rows(10)
                    ->required()
                    ->helperText('Boleh disunting sebelum dikirim.'),

                Placeholder::make('riwayat')
                    ->label('Pengingat Sebelumnya')
                    ->content(function (Loan $record) {
                        $riwayat = $record->reminders()->with('sender:id,name')->limit(3)->get();

                        if ($riwayat->isEmpty()) {
                            return 'Belum pernah diingatkan.';
                        }

                        return new HtmlString(
                            $riwayat->map(fn ($r) => '<div style="font-size:12px;color:#667085">'
                                . $r->sent_at->locale('id')->translatedFormat('d M Y, H:i')
                                . ' oleh ' . e($r->sender?->name ?? '—')
                                . '</div>')->implode('')
                        );
                    }),
            ])
            ->action(function (Loan $record, array $data) {
                $pesan = $data['message'];
                $tautan = ReminderService::waLink($record, $pesan);

                if (! $tautan) {
                    Notification::make()
                        ->danger()
                        ->title('Nomor tidak valid')
                        ->body('Perbaiki nomor HP peminjam terlebih dahulu.')
                        ->send();

                    return;
                }

                ReminderService::log($record, $pesan);

                Notification::make()
                    ->success()
                    ->title('Pengingat dicatat')
                    ->body('WhatsApp akan terbuka. Tekan kirim pada aplikasi untuk menyelesaikan.')
                    ->send();

                // Buka wa.me di tab baru
                return redirect()->away($tautan);
            })
            ->openUrlInNewTab();
    }
}