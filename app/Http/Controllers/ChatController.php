<?php

namespace App\Http\Controllers;

use App\Models\NasaAgroData;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    protected string $systemPrompt = 'Eres un experto agroecológico en Paraguay. Respondes en español, de forma concisa y práctica para pequeños productores. Usas los datos climáticos y NDVI provistos para recomendar siembra, riego y prevención de sequía/heladas.';

    public function index(): View
    {
        return view('chat', [
            'history' => session('chat_history', []),
        ]);
    }

    public function store(Request $request, LlmService $llm)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        /** @var array<int, array{role: string, content: string}> $history */
        $history = session('chat_history', []);
        $history[] = ['role' => 'user', 'content' => $validated['message']];

        $context = $this->buildAgroContext(
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lon']) ? (float) $validated['lon'] : null
        );

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt."\n\nDatos recientes:\n".$context]],
            array_slice($history, -6)
        );

        try {
            $reply = $llm->chat($messages);
        } catch (\RuntimeException $e) {
            session(['chat_history' => array_slice($history, -20)]);

            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 502);
            }

            return redirect()->route('chat.index')->withErrors(['llm' => $e->getMessage()]);
        }

        $history[] = ['role' => 'assistant', 'content' => $reply];
        session(['chat_history' => array_slice($history, -20)]);

        if ($request->wantsJson()) {
            return response()->json(['reply' => $reply]);
        }

        return redirect()->route('chat.index');
    }

    protected function buildAgroContext(?float $lat, ?float $lon): string
    {
        $query = NasaAgroData::query()->orderByDesc('date')->limit(5);

        if ($lat !== null && $lon !== null) {
            $query->near($lat, $lon);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return 'Sin registros recientes en nasa_agro_data.';
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = sprintf(
                'Fecha %s en (%.4F, %.4F): temp %.1F C (máx %s, mín %s), lluvia %.1F mm, humedad %.0F %%, viento %s, NDVI %s.',
                $row->date->format('Y-m-d'),
                (float) $row->lat,
                (float) $row->lon,
                (float) $row->temp,
                $row->temp_max !== null ? number_format((float) $row->temp_max, 1).' C' : 's/d',
                $row->temp_min !== null ? number_format((float) $row->temp_min, 1).' C' : 's/d',
                (float) $row->precip,
                (float) $row->humidity,
                $row->wind_speed !== null ? number_format((float) $row->wind_speed * 3.6, 1).' km/h' : 's/d',
                $row->ndvi_value !== null ? number_format((float) $row->ndvi_value, 3) : 'sin dato'
            );
        }

        return implode("\n", $lines);
    }
}
