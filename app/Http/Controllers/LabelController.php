<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\LabelService;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function single(Request $request, Asset $asset)
    {
        // $this->authorize('view', $asset);
        abort_unless(auth()->check(), 401);
        $this->authorize('view', $asset);
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $assets = Asset::whereKey($asset->getKey())->get();

        $pdf = $request->query('sheet')
            ? LabelService::sheet($assets, (int) $request->query('start', 1))
            : LabelService::thermal($assets);

        return $pdf->stream(LabelService::filename($assets));
    }

    public function batch(Request $request)
    {
        abort_unless(auth()->check(), 401);
        $this->authorize('viewAny', Asset::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'mode' => ['nullable', 'in:thermal,sheet'],
            'start' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $assets = Asset::query()
            ->whereIn('id', $data['ids'])
            ->orderBy('code')
            ->get();

        abort_if($assets->isEmpty(), 404);

        $pdf = ($data['mode'] ?? 'sheet') === 'thermal'
            ? LabelService::thermal($assets)
            : LabelService::sheet($assets, (int) ($data['start'] ?? 1));

        return $pdf->stream(LabelService::filename($assets));
    }
}