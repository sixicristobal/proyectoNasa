<?php

namespace App\Services;

use App\Models\NasaAgroData;
use Illuminate\Support\Facades\Http;

class NasaPowerService
{
    protected string $baseUrl = 'https://power.larc.nasa.gov/api/temporal/daily/point';

    /**
     * @return array<string, array<string, float|null>>
     */
    public function fetchDaily(float $lat, float $lon, string $start, string $end): array
    {
        $response = Http::timeout(60)->get($this->baseUrl, [
            'parameters' => 'T2M,PRECTOTCORR,RH2M',
            'community' => 'AG',
            'longitude' => $lon,
            'latitude' => $lat,
            'start' => str_replace('-', '', $start),
            'end' => str_replace('-', '', $end),
            'format' => 'JSON',
        ]);

        $response->throw();

        return $response->json('properties.parameter', []);
    }

    public function syncToDatabase(float $lat, float $lon, string $start, string $end): int
    {
        $parameters = $this->fetchDaily($lat, $lon, $start, $end);

        $temps = $parameters['T2M'] ?? [];
        $precips = $parameters['PRECTOTCORR'] ?? [];
        $humidities = $parameters['RH2M'] ?? [];

        $rows = [];
        foreach ($temps as $rawDate => $temp) {
            $date = $this->normalizeDate((string) $rawDate);
            $rows[] = [
                'lat' => $lat,
                'lon' => $lon,
                'date' => $date,
                'temp' => $this->nullIfFill($temp),
                'precip' => $this->nullIfFill($precips[$rawDate] ?? null),
                'humidity' => $this->nullIfFill($humidities[$rawDate] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return NasaAgroData::upsertRows($rows);
    }

    protected function normalizeDate(string $rawDate): string
    {
        if (strlen($rawDate) === 8) {
            return substr($rawDate, 0, 4).'-'.substr($rawDate, 4, 2).'-'.substr($rawDate, 6, 2);
        }

        return $rawDate;
    }

    protected function nullIfFill(mixed $value): ?float
    {
        if ($value === null || $value === -999 || $value === '-999' || $value === -999.0) {
            return null;
        }

        return (float) $value;
    }
}
