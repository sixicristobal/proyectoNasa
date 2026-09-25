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
            return redirect()->route('recommendations.index')->withErrors(['llm' => $e->getMessage()]);
        }

        return redirect()->route('recommendations.index')->with('recommendation', $result);
    }

    protected function buildContext(?float $lat, ?float $lon): string
    {
        $query = NasaAgroData::query()->orderByDesc('date')->limit(7);

        if ($lat !== null && $lon !== null) {
            $query->where('lat', $lat)->where('lon', $lon);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return 'Sin registros recientes en nasa_agro_data.';
        }

        return $rows->map(fn ($r) => sprintf(
            'Fecha %s en (%.4F, %.4F): temp %.1F C, lluvia %.1F mm, humedad %.0F %%, NDVI %s.',
            $r->date->format('Y-m-d'),
            (float) $r->lat,
            (float) $r->lon,
            (float) $r->temp,
            (float) $r->precip,
            (float) $r->humidity,
            $r->ndvi_value !== null ? number_format((float) $r->ndvi_value, 3) : 'sin dato'
        ))->implode("\n");
    }
}
