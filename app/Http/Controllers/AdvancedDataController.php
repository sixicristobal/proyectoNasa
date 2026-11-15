<?php

namespace App\Http\Controllers;

use App\Models\Field;
use App\Services\EarthdataService;
use App\Services\Nasa\CmrService;
use App\Services\Nasa\EcostressService;
use App\Services\Nasa\LandsatService;
use App\Services\Nasa\ModisExtendedService;
use App\Services\Nasa\SrtmService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AdvancedDataController extends Controller
{
    public function index(
        Request $request,
        EcostressService $ecostress,
        SrtmService $srtm,
        ModisExtendedService $modisExt,
        LandsatService $landsat,
        CmrService $cmr
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

        $results = array_map(function (array $granule) use ($cmr): array {
            $link = $cmr->downloadableLink($granule);

            return $granule + [
                // URL firmada: evita que la ruta de descarga se use como proxy abierto.
                '_download' => $link !== null
                    ? URL::temporarySignedRoute('advanced.download', now()->addHours(6), ['url' => $link])
                    : null,
            ];
        }, $results);

        return view('advanced-data', [
            'service' => $service,
            'lat' => $lat,
            'lon' => $lon,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'results' => $results,
        ]);
    }

    /**
     * Descarga un gránulo de NASA usando las credenciales Earthdata del servidor,
     * para que el usuario no tenga que pasar por el login de URS.
     */
    public function download(Request $request, EarthdataService $earthdata): BinaryFileResponse|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de descarga expiró o no es válido.');
        }

        $url = (string) $request->query('url', '');

        if (! $earthdata->isAllowedUrl($url)) {
            abort(400, 'El dominio de descarga no está permitido.');
        }

        try {
            $path = $earthdata->downloadToTemporaryFile($url);
        } catch (RequestException $e) {
            report($e);

            $status = $e->response->status();
            $message = in_array($status, [401, 403], true)
                ? __('messages.download_unauthorized')
                : __('messages.download_failed', ['status' => $status]);

            return redirect()->route('advanced.index')->with('download_error', $message);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('advanced.index')
                ->with('download_error', __('messages.download_failed', ['status' => '—']));
        }

        return response()->download($path, $this->downloadFilename($url))
            ->deleteFileAfterSend(true);
    }

    /**
     * Deriva un nombre de archivo seguro a partir de la URL de NASA.
     */
    protected function downloadFilename(string $url): string
    {
        $name = basename((string) parse_url($url, PHP_URL_PATH));
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? '';

        return $name !== '' ? $name : 'nasa-download';
    }
}