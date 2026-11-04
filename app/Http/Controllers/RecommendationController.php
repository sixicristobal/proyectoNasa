<?php

namespace App\Http\Controllers;

use App\Models\NasaAgroData;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecommendationController extends Controller
{
    public function index(): View
    {
        return view('recommendations', ['result' => session('recommendation')]);
    }

    public function store(Request $request, LlmService $llm)
    {
        $validated = $request->validate([
            'topic' => ['required', 'in:siembra,riego,rotacion'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $context = $this->buildContext(
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lon']) ? (float) $validated['lon'] : null
        );

        try {
            $result = $llm->recommend($validated['topic'], $context);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 502);
            }

            return redirect()->route('recommendations.index')->withErrors(['llm' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['result' => $result]);
        }

        return redirect()->route('recommendations.index')->with('recommendation', $result);
    }

    protected function buildContext(?float $lat, ?float $lon): string
    {
        $query = NasaAgroData::query()->orderByDesc('date')->limit(5);

        if ($lat !== null && $lon !== null) {
            $query->near($lat, $lon);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return 'Sin registros recientes en nasa_agro_data.';
        }

        return $rows->map(fn ($r) => sprintf(
            'Fecha %s en (%.4F, %.4F): temp %.1F C (máx %s, mín %s), lluvia %.1F mm, humedad %.0F %%, viento %s, NDVI %s.',
            $r->date->format('Y-m-d'),
            (float) $r->lat,
            (float) $r->lon,
            (float) $r->temp,
            $r->temp_max !== null ? number_format((float) $r->temp_max, 1).' C' : 's/d',
            $r->temp_min !== null ? number_format((float) $r->temp_min, 1).' C' : 's/d',
            (float) $r->precip,
            (float) $r->humidity,
            $r->wind_speed !== null ? number_format((float) $r->wind_speed * 3.6, 1).' km/h' : 's/d',
            $r->ndvi_value !== null ? number_format((float) $r->ndvi_value, 3) : 'sin dato'
        ))->implode("\n");
    }
}
