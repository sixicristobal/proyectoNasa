<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\NasaAgroData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $desde = isset($validated['desde']) ? Carbon::parse($validated['desde'])->format('Y-m-d') : null;
        $hasta = isset($validated['hasta']) ? Carbon::parse($validated['hasta'])->format('Y-m-d') : null;

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw ValidationException::withMessages([
                'desde' => 'La fecha "desde" no puede ser posterior a "hasta".',
            ]);
        }

        $query = NasaAgroData::query()->orderBy('date');

        if (! empty($validated['lat']) && ! empty($validated['lon'])) {
            $query->near((float) $validated['lat'], (float) $validated['lon']);
        }

        if ($desde !== null) {
            $query->where('date', '>=', $desde);
        }

        if ($hasta !== null) {
            $query->where('date', '<=', $hasta);
        }

        $hasRange = $desde !== null || $hasta !== null;
        $records = $query->limit(365)->get();
        $latest = $records->last();
        $recent = $records->slice(-5)->reverse()->values();

        // ── Período de los KPIs de lluvia: el rango elegido, o por defecto los
        //    últimos 30 días con dato (excluye días NULL del lag NRT) ──
        $month = $records->filter(fn ($r) => $r->precip !== null);
        $month = ($hasRange ? $month : $month->slice(-30))->values();
        $rainEvents = $month->filter(fn ($r) => (float) $r->precip >= 10)->sortByDesc('precip')->values();

        $periodLabel = $hasRange
            ? '('.($desde !== null ? Carbon::parse($desde)->format('m-d') : 'inicio')
                .' → '.($hasta !== null ? Carbon::parse($hasta)->format('m-d') : 'hoy').')'
            : '(30 días)';
        $periodTitle = $hasRange
            ? ($desde ?? 'inicio').' → '.($hasta ?? 'hoy')
            : 'últimos 30 días con dato';

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
            'desde' => $desde,
            'hasta' => $hasta,
            'hasRange' => $hasRange,
            'periodLabel' => $periodLabel,
            'periodTitle' => $periodTitle,
            'latest' => $latest,
            'recent' => $recent,
            'total' => $records->count(),
            'alerts' => Alert::query()->latest()->limit(8)->get(),
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $record = NasaAgroData::query()->orderBy('date', 'desc')->first();

        return response()->json([
            'date' => $record?->date?->format('Y-m-d'),
            'ndvi_value' => $record?->ndvi_value,
            'temp' => $record?->temp,
            'humidity' => $record?->humidity,
            'precip' => $record?->precip,
        ]);
    }
}
