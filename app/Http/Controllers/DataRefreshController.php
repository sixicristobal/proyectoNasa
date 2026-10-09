<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DataRefreshController extends Controller
{
    /**
     * Actualización manual del pipeline:
     * 1. nasa:sync (rápido, NASA POWER → MariaDB) en la misma request.
     * 2. agro:check-alerts (lento, llama a Ollama por cada riesgo nuevo)
     *    en segundo plano para no colgar la página.
     * Al terminar el sync, la IA (chat/recomendaciones) ya usa datos frescos.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = isset($validated['lat']) ? (float) $validated['lat'] : -25.2637;
        $lon = isset($validated['lon']) ? (float) $validated['lon'] : -57.5759;

        try {
            Artisan::call('nasa:sync', [
                '--lat' => $lat,
                '--lon' => $lon,
                '--days' => 10,
            ]);
            $syncOutput = trim(Artisan::output());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('dashboard', ['lat' => $lat, 'lon' => $lon])
                ->with('refresh_error', 'No se pudo actualizar desde NASA POWER: '.$e->getMessage());
        }

        $alertsNote = $this->launchAlertsCheck($lat, $lon);

        return redirect()->route('dashboard', ['lat' => $lat, 'lon' => $lon])
            ->with('refresh_status', ($syncOutput !== '' ? $syncOutput.' ' : 'Datos actualizados. ').$alertsNote);
    }

    /**
     * Lanza agro:check-alerts desacoplado (puede tardar minutos por Ollama).
     * En tests no se lanza para no tocar la BD real ni llamar a Ollama.
     */
    protected function launchAlertsCheck(float $lat, float $lon): string
    {
        if (app()->runningUnitTests() || ! function_exists('exec')) {
            return 'Revisión de alertas: se generará con el scheduler (03:30).';
        }

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $log = storage_path('logs/agro-alerts-manual.log');
        $cmd = sprintf(
            '%s %s agro:check-alerts --lat=%s --lon=%s >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg((string) $lat),
            escapeshellarg((string) $lon),
            escapeshellarg($log)
        );

        try {
            exec($cmd);
        } catch (\Throwable $e) {
            report($e);

            return 'Revisión de alertas: se generará con el scheduler (03:30).';
        }

        return 'Revisión de alertas con IA lanzada en segundo plano (ver #alertas en unos minutos).';
    }
}
