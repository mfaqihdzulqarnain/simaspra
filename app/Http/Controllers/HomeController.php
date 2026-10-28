<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\StatsService;
use App\Support\Sapra;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('public.home', [
            'appName' => Sapra::appName(),
            'institution' => Sapra::institutionName(),
            'theme' => Sapra::theme(),

            // Data grafis ringkasan aset (partials.aset-grafis)
            'ringkas' => StatsService::publicSummary(),
            'lab' => StatsService::labSummary(),
            'aktivitas' => StatsService::publicActivity(6),
        ]);
    }

    /** Halaman cek QR manual — untuk yang stikernya rusak atau tanpa kamera */
    public function check()
    {
        return view('public.check', [
            'theme' => Sapra::theme(),
        ]);
    }

    public function redirectToAsset(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        $input = strtoupper(trim($data['code']));

        // Terima kode QR pendek maupun kode aset lengkap
        $asset = Asset::query()
            ->allUnits()
            ->where(fn ($q) => $q->where('public_code', $input)->orWhere('code', $input))
            ->first();

        if (! $asset) {
            return back()
                ->withInput()
                ->with('error', 'Kode tidak ditemukan. Periksa kembali penulisannya.');
        }

        return redirect()->route('asset.public', $asset->public_code);
    }
}