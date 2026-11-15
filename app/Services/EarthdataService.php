<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class EarthdataService
{
    protected string $searchUrl = 'https://cmr.earthdata.nasa.gov/search/granules.json';

    /**
     * Dominios permitidos para descargar archivos de NASA/Earthdata.
     * Evita que la ruta de descarga se use como proxy hacia hosts arbitrarios.
     *
     * @var list<string>
     */
    protected array $allowedHosts = [
        'earthdata.nasa.gov',
        'earthdatacloud.nasa.gov',
        'usgs.gov',
        'nsidc.org',
        'nasa.gov',
    ];

    /**
     * Busca granules MOD13Q1 (NDVI) para un bounding box y rango temporal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchGranules(string $boundingBox, string $temporal): array
    {
        $response = Http::timeout(60)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($this->searchUrl, [
                'short_name' => 'MOD13Q1',
                'version' => '061',
                'bounding_box' => $boundingBox,
                'temporal' => $temporal,
                'page_size' => 10,
                'sort_key' => '-start_date',
            ]);

        $response->throw();

        return $response->json('feed.entry', []);
    }

    /**
     * Descarga un HDF/TIF de Earthdata con Bearer token (o Basic Auth como
     * alternativa) + CookieJar para las redirecciones URS.
     */
    public function downloadFile(string $url, string $filename): string
    {
        $jar = new CookieJar;
        $token = (string) config('services.earthdata.token');

        $client = $token !== ''
            ? Http::withToken($token)
            : Http::withBasicAuth(
                (string) config('services.earthdata.username'),
                (string) config('services.earthdata.password')
            );

        $response = $client->withOptions([
            'cookies' => $jar,
            'allow_redirects' => [
                'max' => 10,
                'track_redirects' => true,
            ],
        ])->timeout(300)->get($url);

        $response->throw();

        $path = 'nasa/'.$filename;
        Storage::disk('local')->put($path, $response->body());

        return Storage::disk('local')->path($path);
    }

    /**
     * Indica si la URL es https y pertenece a un dominio de datos de NASA.
     */
    public function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return false;
        }

        foreach ($this->allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Descarga un archivo autenticado a un archivo temporal y devuelve su ruta.
     * Usa Bearer token o Basic Auth + CookieJar para las redirecciones de URS.
     *
     * @throws Throwable
     */
    public function downloadToTemporaryFile(string $url): string
    {
        if (! $this->isAllowedUrl($url)) {
            throw new InvalidArgumentException('La URL de descarga no pertenece a un dominio de NASA permitido.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'nasa_download_');

        if ($temporaryPath === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de descarga.');
        }

        $token = (string) config('services.earthdata.token');

        $client = $token !== ''
            ? Http::withToken($token)
            : Http::withBasicAuth(
                (string) config('services.earthdata.username'),
                (string) config('services.earthdata.password')
            );

        try {
            $response = $client->withOptions([
                'sink' => $temporaryPath,
                'cookies' => new CookieJar,
                'allow_redirects' => [
                    'max' => 10,
                    'track_redirects' => true,
                ],
            ])->timeout(600)->get($url);

            $response->throw();
        } catch (Throwable $e) {
            @unlink($temporaryPath);

            throw $e;
        }

        return $temporaryPath;
    }
}
