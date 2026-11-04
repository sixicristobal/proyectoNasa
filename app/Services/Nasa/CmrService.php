<?php

namespace App\Services\Nasa;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CmrService
{
    protected string $searchUrl;

    public function __construct()
    {
        $this->searchUrl = (string) config(
            'services.cmr.search_url',
            'https://cmr.earthdata.nasa.gov/search/granules.json'
        );
    }

    /**
     * Busca gránulos en NASA CMR para un punto y ventana temporal.
     *
     * @param  array<string, mixed>  $extra  Parámetros adicionales de CMR (version, sort_key, etc.).
     * @return array<int, array<string, mixed>>
     */
    public function searchGranules(
        string $shortName,
        float $lat,
        float $lon,
        Carbon $start,
        Carbon $end,
        int $pageSize = 10,
        array $extra = []
    ): array {
        $this->assertCoordinates($lat, $lon);

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('La fecha de inicio no puede ser posterior a la de fin.');
        }

        $query = array_merge([
            'short_name' => $shortName,
            'point' => "{$lon},{$lat}",
            'temporal' => $start->copy()->utc()->toIso8601ZuluString()
                .','.$end->copy()->endOfDay()->utc()->toIso8601ZuluString(),
            'page_size' => $pageSize,
        ], $extra);

        Log::info('[NASA CMR] search granules', [
            'short_name' => $shortName,
            'point' => $query['point'],
            'temporal' => $query['temporal'],
        ]);

        $response = Http::timeout(60)
            ->acceptJson()
            ->retry(3, 1000, throw: false)
            ->get($this->searchUrl, $query);

        $response->throw();

        return (array) $response->json('feed.entry', []);
    }

    /**
     * Devuelve el primer link de descarga de datos del gránulo.
     *
     * @param  array<string, mixed>  $granule
     */
    public function downloadableLink(array $granule): ?string
    {
        foreach ((array) ($granule['links'] ?? []) as $link) {
            $href = (string) ($link['href'] ?? '');
            $rel = (string) ($link['rel'] ?? '');

            if ($href === '') {
                continue;
            }

            if (str_contains(strtolower($rel), 'browse')
                || str_contains(strtolower($rel), 'metadata')
                || str_contains(strtolower($rel), 'documentation')) {
                continue;
            }

            if (preg_match('/\.(hdf5|h5|hdf|nc4|nc|tif|tiff)(\?.*)?$/i', $href)) {
                return $href;
            }

            if (str_contains($rel, '/data#')) {
                return $href;
            }
        }

        return null;
    }

    public function assertCoordinates(float $lat, float $lon): void
    {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException("Latitud inválida: {$lat}. Debe estar entre -90 y 90.");
        }

        if ($lon < -180 || $lon > 180) {
            throw new InvalidArgumentException("Longitud inválida: {$lon}. Debe estar entre -180 y 180.");
        }
    }
}
