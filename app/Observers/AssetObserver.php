<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\NumberSequence;
use App\Support\Sapra;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AssetObserver
{
    public function creating(Asset $asset): void
    {
        $asset->uuid ??= (string) Str::uuid();
        $asset->public_code ??= Asset::generatePublicCode();
        $asset->year_acquired ??= (int) now()->format('Y');
        $asset->code ??= $this->generateCode($asset);
        $asset->created_by ??= Auth::id();

        if (blank($asset->useful_life_years)) {
            $asset->useful_life_years = $asset->category?->useful_life_years;
        }
    }

    /**
     * Format: PA-26-LAP-0001
     * prefix instansi - 2 digit tahun - kode kategori - urut per kategori per tahun
     */
    protected function generateCode(Asset $asset): string
    {
        $prefix = Sapra::assetCodePrefix();
        $year = substr((string) $asset->year_acquired, -2);
        $categoryCode = $asset->category?->code ?? 'UMUM';

        $sequenceType = 'aset_' . strtolower($categoryCode);
        $number = NumberSequence::next($sequenceType, (int) $asset->year_acquired);

        return sprintf(
            '%s-%s-%s-%s',
            $prefix,
            $year,
            strtoupper($categoryCode),
            str_pad((string) $number, 4, '0', STR_PAD_LEFT)
        );
    }
}