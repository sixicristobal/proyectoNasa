<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class EarthdataService
{
    protected string $searchUrl = 'https://cmr.earthdata.nasa.gov/search/granules.json';

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
}
