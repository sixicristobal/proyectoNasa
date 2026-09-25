<?php

namespace App\Http\Controllers;

use App\Models\NasaAgroData;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReforestationController extends Controller
{
    /** Especies nativas de Paraguay con aptitud por suelo y clima. */
    protected array $species = [
        ['nombre' => 'Lapacho (Tabebuia spp.)', 'suelos' => ['rojo', 'arcilloso'], 'clima' => 'Tolera sequía moderada; ideal Oriental húmeda.'],
        ['nombre' => "Kurupa'y (Anadenanthera colubrina)", 'suelos' => ['arenoso', 'rojo'], 'clima' => 'Muy tolerante a sequía; apto Chaco y Occidental.'],
        ['nombre' => 'Yvyra pytã (Peltophorum dubium)', 'suelos' => ['rojo', 'arcilloso', 'arenoso'], 'clima' => 'Rápido crecimiento; tolera heladas leves.'],
        ['nombre' => 'Petereby (Cordia trichotoma)', 'suelos' => ['rojo', 'arcilloso'], 'clima' => 'Madera valiosa; requiere lluvias >1200 mm/año.'],
        ['nombre' => 'Cedro (Cedrela fissilis)', 'suelos' => ['rojo'], 'clima' => 'Sensible a heladas fuertes; prefiere valles húmedos.'],
        ['nombre' => 'Guajayvi (Patagonula americana)', 'suelos' => ['arcilloso', 'rojo'], 'clima' => 'Rústica; buena para cortinas rompeviento.'],
    ];

    public function index(): View
    {
        return view('reforestation', [
            'species' => $this->species,
            'result' => session('reforestation'),
        ]);
    }

    public function store(Request $request, OllamaService $ollama)
    {
        $validated = $request->validate([
            'suelo' => ['required', 'string', 'max:50'],
            'objetivo' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $catalog = collect($this->species)->map(
            fn ($s) => "- {$s['nombre']} | Suelos: ".implode(', ', $s['suelos'])." | {$s['clima']}"
        )->implode("\n");

        $climate = $this->buildClimate(
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lon']) ? (float) $validated['lon'] : null
        );

        $messages = [[
            'role' => 'user',
            'content' => "Eres un experto agroforestal en Paraguay. Suelo del productor: {$validated['suelo']}. "
                .'Objetivo: '.($validated['objetivo'] ?? 'reforestación general').".\n\n"
                ."Catálogo de especies nativas (usa SOLO estas):\n{$catalog}\n\n"
                ."Clima reciente en la parcela:\n{$climate}\n\n"
                .'Recomienda 2-3 especies del catálogo compatibles con el suelo y el clima, con época de plantación y cuidados. Responde en español, conciso.',
        ]];

        try {
            $result = $ollama->chat($messages);
        } catch (\RuntimeException $e) {
            return redirect()->route('reforestation.index')->withErrors(['ollama' => $e->getMessage()]);
        }

        return redirect()->route('reforestation.index')->with('reforestation', $result);
    }

    protected function buildClimate(?float $lat, ?float $lon): string
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
            'Fecha %s: temp %.1F C, lluvia %.1F mm, humedad %.0F %%, NDVI %s.',
            $r->date->format('Y-m-d'),
            (float) $r->temp,
            (float) $r->precip,
            (float) $r->humidity,
            $r->ndvi_value !== null ? number_format((float) $r->ndvi_value, 3) : 'sin dato'
        ))->implode("\n");
    }
}
