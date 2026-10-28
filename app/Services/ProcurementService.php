<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\RequestStatus;
use App\Models\Approval;
use App\Models\NumberSequence;
use App\Models\ProcurementRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Filament\Resources\ProcurementRequests\ProcurementRequestResource;

class ProcurementService
{
    /** Unit mengajukan — draf menjadi resmi */
    public static function submit(ProcurementRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request->forceFill([
                'request_number' => $request->request_number
                    ?: NumberSequence::format('pengajuan', 'PGJ', $request->budget_year),
                'status' => RequestStatus::VerifikasiSarpras,
                'submitted_at' => now(),
            ])->save();

            Approval::record($request, ApprovalAction::Setuju, 1, 'pengaju', 'Pengajuan dikirim');
        });

        NotifyService::toRoles(
            ['kabag_sarpras'],
            'Pengajuan aset baru',
            $request->fresh()->request_number . ' dari ' . $request->unit?->name,
            ProcurementRequestResource::getUrl('view', ['record' => $request]),
            'warning',
            'heroicon-o-clipboard-document-list',
        );
    }

    /** Ka. Sarpras memverifikasi, lanjut ke Wadir */
    public static function verify(ProcurementRequest $request, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $note) {
            $request->forceFill([
                'status' => RequestStatus::MenungguWadir,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'verification_note' => $note,
            ])->save();

            Approval::record($request, ApprovalAction::Setuju, 2, 'kabag_sarpras', $note);
        });

        NotifyService::toRoles(
            ['wadir'],
            'Pengajuan menunggu persetujuan',
            $request->request_number . ' dari ' . $request->unit?->name,
            ProcurementRequestResource::getUrl('view', ['record' => $request]),
            'warning',
            'heroicon-o-clipboard-document-check',
        );
    }


    /** Dikembalikan ke unit untuk diperbaiki */
    public static function requestRevision(ProcurementRequest $request, string $note): void
    {
        DB::transaction(function () use ($request, $note) {
            $request->forceFill([
                'status' => RequestStatus::Revisi,
                'verification_note' => $note,
            ])->save();

            Approval::record($request, ApprovalAction::Revisi, 2, 'kabag_sarpras', $note);
        });

        NotifyService::toUser(
            $request->requested_by,
            'Pengajuan perlu revisi',
            $request->request_number . ' — ' . $note,
            ProcurementRequestResource::getUrl('view', ['record' => $request]),
            'warning',
            'heroicon-o-arrow-uturn-left',
        );
    }

    /** Wadir menyetujui */
    public static function approve(ProcurementRequest $request, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $note) {
            $request->forceFill([
                'status' => RequestStatus::Disetujui,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ])->save();

            Approval::record($request, ApprovalAction::Setuju, 3, 'wadir', $note);
        });

        NotifyService::toUser(
            $request->requested_by,
            'Pengajuan disetujui',
            $request->request_number . ' telah disetujui Wakil Direktur.',
            ProcurementRequestResource::getUrl('view', ['record' => $request]),
            'success',
            'heroicon-o-check-badge',
        );

        NotifyService::toRoles(
            ['kabag_sarpras'],
            'Pengajuan siap disalurkan',
            $request->request_number . ' — ' . $request->unit?->name,
            ProcurementRequestResource::getUrl('view', ['record' => $request]),
            'success',
            'heroicon-o-truck',
        );
    }

    /** Ditolak — status akhir, tidak bisa dilanjutkan */
    public static function reject(ProcurementRequest $request, string $reason, string $role): void
    {
        DB::transaction(function () use ($request, $reason, $role) {
            $request->forceFill([
                'status' => RequestStatus::Ditolak,
                'rejection_reason' => $reason,
            ])->save();

            Approval::record($request, ApprovalAction::Tolak, $role === 'wadir' ? 3 : 2, $role, $reason);
        });

        NotifyService::toUser(
            $request->requested_by,
            'Pengajuan ditolak',
            $request->request_number . ' — ' . $reason,
            ProcurementRequestResource::getUrl('view', ['record' => $request]),
            'danger',
            'heroicon-o-x-circle',
        );
    }

    /**
     * Cari aset serupa yang menganggur di unit lain.
     * Mencegah belanja ganda — temuan audit paling umum.
     */
    public static function similarIdleAssets(ProcurementRequest $request): \Illuminate\Support\Collection
    {
        $categoryIds = $request->items()->pluck('asset_category_id')->unique();

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return \App\Models\Asset::query()
            ->allUnits()
            ->with('unit')
            ->whereIn('asset_category_id', $categoryIds)
            ->where('unit_id', '!=', $request->unit_id)
            ->where('status', \App\Enums\AssetStatus::Aktif)
            ->where('condition', \App\Enums\AssetCondition::Baik)
            ->limit(10)
            ->get();
    }
}