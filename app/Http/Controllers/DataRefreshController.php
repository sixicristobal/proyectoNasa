<?php

namespace App\Http\Controllers;

use App\Console\Commands\SyncNasaPowerData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

class DataRefreshController extends Controller
{
    /**
     * Actualización manual del pipeline:
     * 1. nasa:sync (rápido, NASA POWER → MariaDB) en la misma request.
     * 2. agro:check-alerts (lento, llama a la IA por cada riesgo nuevo)
     *    en segundo plano para no colgar la página.
     * Al terminar el sync, la IA (chat/recomendaciones) ya usa datos frescos.
     *
     * Rango: days (10 por defecto) o desde/hasta explícitos (mandan sobre days).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $lat = isset($validated['lat']) ? (float) $validated['lat'] : -25.2637;
        $lon = isset($validated['lon']) ? (float) $validated['lon'] : -57.5759;

        [$start, $end, $windowDays] = $this->resolveRange($validated);

        try {
            Artisan::call('nasa:sync', [
                '--lat' => $lat,
                '--lon' => $lon,
                '--start' => $start,
                '--end' => $end,
            ]);
            $syncOutput = trim(Artisan::output());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('dashboard', ['lat' => $lat, 'lon' => $lon])
                ->with('refresh_error', __('messages.refresh_failed', ['error' => $e->getMessage()]));
        }

        $alertsNote = $this->launchAlertsCheck($lat, $lon, $windowDays, app()->getLocale());

        return redirect()->route('dashboard', ['lat' => $lat, 'lon' => $lon])
            ->with('refresh_status', ($syncOutput !== '' ? $syncOutput : __('messages.refresh_succeeded')).' '.$alertsNote);
    }

    /**
     * Convierte days/desde/hasta en una ventana concreta y valida su tamaño.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string, 2: int}
     *
     * @throws ValidationException
     */
    protected function resolveRange(array $validated): array
    {
        $desde = isset($validated['desde']) ? Carbon::parse($validated['desde'])->startOfDay() : null;
        $hasta = isset($validated['hasta']) ? Carbon::parse($validated['hasta'])->startOfDay() : null;

        if ($desde !== null && $hasta !== null && $desde->greaterThan($hasta)) {
            throw ValidationException::withMessages([
                'desde' => __('messages.date_from_after_to'),
            ]);
        }

        $end = $hasta ?? now()->startOfDay();
        $start = $desde ?? $end->copy()->subDays(max(1, (int) ($validated['days'] ?? 10)) - 1);

        $windowDays = (int) $start->diffInDays($end) + 1;
        if ($windowDays > SyncNasaPowerData::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'desde' => __('messages.date_range_too_long', [
                    'max' => SyncNasaPowerData::MAX_RANGE_DAYS,
                    'selected' => $windowDays,
                ]),
            ]);
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d'), $windowDays];
    }

    /**
     * Lanza agro:check-alerts desacoplado (puede tardar minutos por la IA).
     * En tests no se lanza para no tocar la BD real ni llamar al proveedor de IA.
     */
    protected function launchAlertsCheck(float $lat, float $lon, int $days, string $locale): string
    {
        if (app()->runningUnitTests() || ! function_exists('exec')) {
            return __('messages.alerts_scheduled');
        }

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $log = storage_path('logs/agro-alerts-manual.log');
        $cmd = sprintf(
            '%s %s agro:check-alerts --lat=%s --lon=%s --days=%d --locale=%s >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg((string) $lat),
            escapeshellarg((string) $lon),
            $days,
            escapeshellarg($locale),
            escapeshellarg($log)
        );

        try {
            exec($cmd);
        } catch (\Throwable $e) {
            report($e);

            return __('messages.alerts_scheduled');
        }

        return __('messages.alerts_started');
    }
}
