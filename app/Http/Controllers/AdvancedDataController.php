<?php

namespace App\Http\Controllers;

use App\Models\Field;
use App\Services\Nasa\EcostressService;
use App\Services\Nasa\LandsatService;
use App\Services\Nasa\ModisExtendedService;
use App\Services\Nasa\SrtmService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvancedDataController extends Controller
{
    public function index(
        Request $request,
        EcostressService $ecostress,
        SrtmService $srtm,
        ModisExtendedService $modisExt,
        LandsatService $landsat
    ): View {
        $service = (string) $request->query('servicio', 'ecostress');
        $lat = (float) $request->query('lat', -25.2637);
        $lon = (float) $request->query('lon', -57.5759);
        $start = Carbon::parse($request->query('inicio', now()->subDays(30)->toDateString()));
        $end = Carbon::parse($request->query('fin', now()->toDateString()));

        $field = new Field();
        $field->latitude = $lat;
        $field->longitude = $lon;

        $results = match ($service) {
            'srtm' => $srtm->fetch($field, $start, $end),
            'modis_lst' => $modisExt->fetchLst($field, $start, $end),
            'modis_et' => $modisExt->fetchEt($field, $start, $end),
            'landsat' => $landsat->fetch($field, $start, $end),
            default => $ecostress->fetch($field, $start, $end),
        };

        return view('advanced-data', [
            'service' => $service,
            'lat' => $lat,
            'lon' => $lon,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'results' => $results,
        ]);
    }
}