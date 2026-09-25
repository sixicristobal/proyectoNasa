<?php

namespace App\Services;

use App\Models\NasaAgroData;
use Illuminate\Support\Facades\Process;

class NdviProcessor
{
    public function __construct(protected EarthdataService $earthdata) {}

    /**
     * Descarga el archivo y extrae el NDVI para lat/lon, guardándolo en MySQL.
     */
    public function processUrl(string $fileUrl, float $lat, float $lon, string $date): ?float
    {
        $filename = basename(parse_url($fileUrl, PHP_URL_PATH) ?: 'nasa_'.time().'.hdf');
        $localPath = $this->earthdata->downloadFile($fileUrl, $filename);

        return $this->extractAndStore($localPath, $lat, $lon, $date);
    }

    public function extractAndStore(string $localPath, float $lat, float $lon, string $date): ?float
    {
        $ndvi = $this->extractNdvi($localPath, $lat, $lon);

        if ($ndvi === null) {
            return null;
        }

        NasaAgroData::upsertRows([[
            'lat' => $lat,
            'lon' => $lon,
            'date' => $date,
            'ndvi_value' => $ndvi,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        return $ndvi;
    }

    public function extractNdvi(string $localPath, float $lat, float $lon): ?float
    {
        $script = base_path('storage/scripts/extract_ndvi.py');

        $result = Process::timeout(120)->run([
            'python3',
            $script,
            $localPath,
            (string) $lat,
            (string) $lon,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('extract_ndvi.py failed: '.$result->errorOutput());
        }

        $payload = json_decode($result->output(), true);

        return isset($payload['ndvi']) && $payload['ndvi'] !== null
            ? (float) $payload['ndvi']
            : null;
    }
}
