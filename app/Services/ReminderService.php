<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanReminder;
use App\Support\Phone;
use App\Support\Sapra;
use Illuminate\Support\Facades\Auth;

class ReminderService
{
    /** Susun isi pesan dari templat, ganti seluruh kata kunci */
    public static function compose(Loan $loan, ?string $template = null): string
    {
        $template ??= Sapra::reminderTemplate();

        $alat = $loan->items()
            ->with('asset:id,code,name')
            ->get()
            ->map(fn ($item) => trim(($item->asset?->code ?? '') . ' ' . ($item->asset?->name ?? ''))
                . ($item->quantity > 1 ? ' × ' . $item->quantity : ''))
            ->filter()
            ->implode(', ');

        return strtr($template, [
            '{nama}' => $loan->borrower_name,
            '{nomor}' => $loan->loan_number,
            '{alat}' => $alat ?: '—',
            '{lab}' => $loan->laboratory?->name ?? 'Umum',
            '{batas}' => $loan->end_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—',
            '{terlambat}' => $loan->late_label ?? '—',
            '{instansi}' => Sapra::institutionName(),
        ]);
    }

    /** Tautan wa.me siap buka, null bila nomor tidak valid */
    public static function waLink(Loan $loan, ?string $message = null): ?string
    {
        return Phone::waLink($loan->borrower_phone, $message ?? static::compose($loan));
    }

    /** Catat bahwa pengingat dikirim */
    public static function log(Loan $loan, string $message): LoanReminder
    {
        return LoanReminder::create([
            'loan_id' => $loan->getKey(),
            'sent_by' => Auth::id(),
            'phone' => Phone::normalize($loan->borrower_phone) ?? '',
            'message' => $message,
            'sent_at' => now(),
        ]);
    }

    /** Pinjaman yang pantas diingatkan: terlambat atau jatuh tempo hari ini */
    public static function needsReminder(Loan $loan): bool
    {
        return $loan->is_overdue
            || ($loan->isOngoing() && $loan->end_at?->isToday());
    }
}