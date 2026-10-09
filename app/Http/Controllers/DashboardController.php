<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\NasaAgroData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $query = NasaAgroData::query()->orderBy('date');

        if (! empty($validated['lat']) && ! empty($validated['lon'])) {
            $query->near((float) $validated['lat'], (float) $validated['lon']);
        }

        $records = $query->limit(365)->get();
        $latest = $records->last();
        $recent = $records->slice(-5)->reverse()->values();

        // ── Último mes con dato real (excluye días NULL del lag NRT) ──
        $month = $records->filter(fn ($r) => $r->precip !== null)->slice(-30)->values();
        $rainEvents = $month->filter(fn ($r) => (float) $r->precip >= 10)->sortByDesc('precip')->values();

        return view('dashboard', [
            'labels' => $records->map(fn ($r) => $r->date->format('Y-m-d'))->all(),
            'ndvi' => $records->map(fn ($r) => $r->ndvi_value)->all(),
            'temps' => $records->map(fn ($r) => $r->temp)->all(),
            'precips' => $records->map(fn ($r) => $r->precip)->all(),
            'monthLabels' => $month->map(fn ($r) => $r->date->format('m-d'))->all(),
            'monthPrecips' => $month->map(fn ($r) => $r->precip)->all(),
            'monthAccum' => round((float) $month->sum('precip'), 1),
            'monthRainDays' => $month->filter(fn ($r) => (float) $r->precip >= 1)->count(),
            'rainEvents' => $rainEvents,
            'lat' => $validated['lat'] ?? null,
            'lon' => $validated['lon'] ?? null,
            'latest' => $latest,
            'recent' => $recent,
            'total' => $records->count(),
            'alerts' => Alert::query()->latest()->limit(8)->get(),
        ]);
    }
}
