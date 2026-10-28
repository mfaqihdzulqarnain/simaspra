<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\DamageSeverity;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\DamageReport;
use App\Models\Room;
use App\Services\OpnameService;
use App\Services\QrService;
use App\Support\Sapra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicAssetController extends Controller
{
    public function show(string $code)
    {
        if (! Sapra::isPublicQrEnabled() && ! Auth::check()) {
            abort(404);
        }

        $asset = $this->findAsset($code);

        $opname = null;
        $opnameItem = null;

        // Panel opname hanya untuk petugas yang login
        if (Auth::check()) {
            $opname = OpnameService::activeFor($asset);

            if ($opname) {
                $opnameItem = OpnameService::itemFor($opname, $asset);
            }
        }

        return view('public.asset', [
            'asset' => $asset,
            'qr' => QrService::forAsset($asset, 160),
            'canReport' => Sapra::isPublicDamageReportEnabled() || Auth::check(),
            'isInternal' => Auth::check(),
            'opname' => $opname,
            'opnameItem' => $opnameItem,
            'rooms' => Auth::check()
                ? Room::query()->with('building')->where('is_active', true)->orderBy('code')->get()
                : collect(),
        ]);
    }

    public function report(Request $request, string $code)
    {
        if (! Sapra::isPublicDamageReportEnabled() && ! Auth::check()) {
            abort(403);
        }

        $asset = $this->findAsset($code);

        $data = $request->validate([
            'reporter_name' => ['required', 'string', 'max:100'],
            'reporter_contact' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'severity' => ['required', Rule::in(DamageSeverity::values())],
            'website' => ['nullable', 'size:0'], // honeypot
        ]);

        // Bot mengisi field tersembunyi: pura-pura sukses, jangan simpan
        if (filled($request->input('website'))) {
            return back()->with('status', 'Laporan diterima.');
        }

        DamageReport::create([
            'asset_id' => $asset->id,
            'reporter_name' => $data['reporter_name'],
            'reporter_contact' => $data['reporter_contact'] ?? null,
            'description' => $data['description'],
            'severity' => $data['severity'],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Laporan kerusakan berhasil dikirim. Terima kasih.');
    }

    protected function findAsset(string $code): Asset
    {
        $asset = Asset::query()
            ->allUnits()
            ->with(['category', 'unit', 'room.building'])
            ->byPublicCode($code)
            ->first();

        if (! $asset) {
            throw new NotFoundHttpException('Aset tidak ditemukan.');
        }

        return $asset;
    }

        public function recordOpname(Request $request, string $code)
    {
        $asset = $this->findAsset($code);
        $opname = OpnameService::activeFor($asset);

        if (! $opname) {
            return back()->with('opname_error', 'Tidak ada periode opname yang sedang berjalan untuk aset ini.');
        }

        $data = $request->validate([
            'found_room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'condition' => ['nullable', Rule::in(AssetCondition::values())],
            'note' => ['nullable', 'string', 'max:500'],
            'not_found' => ['nullable', 'boolean'],
            'found_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        // Ditandai tidak ditemukan
        if ($request->boolean('not_found')) {
            $item = OpnameService::itemFor($opname, $asset);

            if (! $item) {
                return back()->with('opname_error', 'Aset ini tidak ada dalam daftar periksa.');
            }

            $item->markNotFound($data['note'] ?? null);

            return back()->with('opname_status', 'Ditandai tidak ditemukan.');
        }

        $result = OpnameService::scan(
            $opname,
            $asset->public_code,
            $data['found_room_id'] ?? null,
            OpnameService::toCondition($data['condition'] ?? null),
            $data['note'] ?? null,
            $asset->isCollective() && isset($data['found_quantity']) ? (int) $data['found_quantity'] : null,
        );

        return $result['ok']
            ? back()->with('opname_status', $result['message'])
            : back()->with('opname_error', $result['message']);
    }
}