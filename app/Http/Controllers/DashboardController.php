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
            $query->where('lat', $validated['lat'])->where('lon', $validated['lon']);
        }

        $records = $query->limit(365)->get();

        return view('dashboard', [
            'labels' => $records->map(fn ($r) => $r->date->format('Y-m-d'))->all(),
            'ndvi' => $records->map(fn ($r) => $r->ndvi_value)->all(),
            'temps' => $records->map(fn ($r) => $r->temp)->all(),
            'lat' => $validated['lat'] ?? null,
            'lon' => $validated['lon'] ?? null,
            'alerts' => Alert::query()->latest()->limit(5)->get(),
        ]);
    }
}
