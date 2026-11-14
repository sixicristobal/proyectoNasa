<?php

namespace App\Http\Controllers;

use App\Models\NasaAgroData;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReforestationController extends Controller
{
    /** Especies nativas de Paraguay con aptitud por suelo y clima. */
    protected array $species = [
        ['nombre' => 'Lapacho (Tabebuia spp.)', 'soil_keys' => ['red', 'clay'], 'climate_key' => 'lapacho'],
        ['nombre' => "Kurupa'y (Anadenanthera colubrina)", 'soil_keys' => ['sandy', 'red'], 'climate_key' => 'kurupay'],
        ['nombre' => 'Yvyra pytã (Peltophorum dubium)', 'soil_keys' => ['red', 'clay', 'sandy'], 'climate_key' => 'yvyra_pyta'],
        ['nombre' => 'Petereby (Cordia trichotoma)', 'soil_keys' => ['red', 'clay'], 'climate_key' => 'petereby'],
        ['nombre' => 'Cedro (Cedrela fissilis)', 'soil_keys' => ['red'], 'climate_key' => 'cedar'],
        ['nombre' => 'Guajayvi (Patagonula americana)', 'soil_keys' => ['clay', 'red'], 'climate_key' => 'guajayvi'],
    ];

    public function index(): View
    {
        return view('reforestation', [
            'species' => $this->localizedSpecies(),
            'result' => session('reforestation'),
        ]);
    }

    public function store(Request $request, LlmService $llm)
    {
        $validated = $request->validate([
            'suelo' => ['required', 'string', 'max:50'],
            'objetivo' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $catalog = collect($this->localizedSpecies())->map(
            fn ($s) => "- {$s['nombre']} | Suelos: ".implode(', ', $s['suelos'])." | {$s['clima']}"
        )->implode("\n");

        $climate = $this->buildClimate(
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lon']) ? (float) $validated['lon'] : null
        );

        $messages = [[
            'role' => 'user',
            'content' => "You are an agroforestry expert in Paraguay. Farmer's soil type: {$validated['suelo']}. "
                .'Objective: '.($validated['objetivo'] ?? 'general reforestation').".\n\n"
                ."Native species catalog (use ONLY these species):\n{$catalog}\n\n"
                ."Recent field climate:\n{$climate}\n\n"
                .'Recommend 2–3 species from the catalog that fit the soil and climate, including planting season and care. '
                .__('messages.prompt_language', ['language' => app()->getLocale() === 'en' ? 'English' : 'Spanish']),
        ]];

        try {
            $result = $llm->chat($messages);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 502);
            }

            return redirect()->route('reforestation.index')->withErrors(['llm' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['result' => $result]);
        }

        return redirect()->route('reforestation.index')->with('reforestation', $result);
    }

    protected function buildClimate(?float $lat, ?float $lon): string
    {
        $query = NasaAgroData::query()->orderByDesc('date')->limit(5);

        if ($lat !== null && $lon !== null) {
            $query->near($lat, $lon);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return __('messages.no_recent_data');
        }

        return $rows->map(fn ($r) => sprintf(
            'Fecha %s: temp %.1F C (máx %s, mín %s), lluvia %.1F mm, humedad %.0F %%, viento %s, NDVI %s.',
            $r->date->format('Y-m-d'),
            (float) $r->temp,
            $r->temp_max !== null ? number_format((float) $r->temp_max, 1).' C' : 's/d',
            $r->temp_min !== null ? number_format((float) $r->temp_min, 1).' C' : 's/d',
            (float) $r->precip,
            (float) $r->humidity,
            $r->wind_speed !== null ? number_format((float) $r->wind_speed * 3.6, 1).' km/h' : 's/d',
            $r->ndvi_value !== null ? number_format((float) $r->ndvi_value, 3) : 'sin dato'
        ))->implode("\n");
    }

    /**
     * @return array<int, array{nombre: string, suelos: array<int, string>, clima: string}>
     */
    protected function localizedSpecies(): array
    {
        return array_map(static function (array $species): array {
            return [
                'nombre' => $species['nombre'],
                'suelos' => array_map(
                    static fn (string $soil): string => __("messages.species_soils.{$soil}"),
                    $species['soil_keys']
                ),
                'clima' => __("messages.species_climate.{$species['climate_key']}"),
            ];
        }, $this->species);
    }
}
