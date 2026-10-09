<?php

namespace App\Http\Controllers;

use App\Services\NasaImageryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImageryController extends Controller
{
    public function index(Request $request, NasaImageryService $imagery): View
    {
        $validated = $request->validate([
            'fecha' => ['nullable', 'date'],
            'capa' => ['nullable', 'string', 'max:30'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $date = $imagery->normalizeDate($validated['fecha'] ?? null);
        $layer = $imagery->normalizeLayer($validated['capa'] ?? null);
        $lat = isset($validated['lat']) ? (float) $validated['lat'] : -25.2637;
        $lon = isset($validated['lon']) ? (float) $validated['lon'] : -57.5759;

        $templates = [];
        foreach (array_keys($imagery->layers()) as $key) {
            $templates[$key] = $imagery->tileUrlTemplate($key, $date);
        }

        return view('imagery', [
            'layers' => $imagery->layers(),
            'layer' => $layer,
            'date' => $date,
            'lat' => $lat,
            'lon' => $lon,
            'templates' => $templates,
            'snapshot' => $imagery->snapshotUrl($layer, $date, $lat, $lon),
            'worldview' => $imagery->worldviewUrl($layer, $date, $lat, $lon),
            'attribution' => $imagery->attribution(),
        ]);
    }
}
