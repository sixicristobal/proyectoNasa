<?php

namespace App\Services;

use App\Models\NasaAgroData;
use Illuminate\Support\Facades\Http;

class NasaPowerService
{
    protected string $baseUrl = 'https://power.larc.nasa.gov/api/temporal/daily/point';

    /**
     * Parámetros AG de NASA POWER:
     * T2M/T2M_MAX/T2M_MIN (temp), PRECTOTCORR (lluvia), RH2M (humedad),
     * WS2M (viento m/s, para tormentas), ALLSKY_SFC_SW_DWN (radiación solar).
     */
    protected string $parameters = 'T2M,T2M_MAX,T2M_MIN,PRECTOTCORR,RH2M,WS2M,ALLSKY_SFC_SW_DWN';

    /**
     * @return array<string, array<string, float|null>>
     */
    public function fetchDaily(float $lat, float $lon, string $start, string $end): array
    {
        $response = Http::timeout(60)->get($this->baseUrl, [
            'parameters' => $this->parameters,
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
        $tempsMax = $parameters['T2M_MAX'] ?? [];
        $tempsMin = $parameters['T2M_MIN'] ?? [];
        $precips = $parameters['PRECTOTCORR'] ?? [];
        $humidities = $parameters['RH2M'] ?? [];
        $winds = $parameters['WS2M'] ?? [];
        $solar = $parameters['ALLSKY_SFC_SW_DWN'] ?? [];

        $rows = [];
        foreach ($temps as $rawDate => $temp) {
            $date = $this->normalizeDate((string) $rawDate);
            $rows[] = [
                'lat' => $lat,
                'lon' => $lon,
                'date' => $date,
                'temp' => $this->nullIfFill($temp),
                'temp_max' => $this->nullIfFill($tempsMax[$rawDate] ?? null),
                'temp_min' => $this->nullIfFill($tempsMin[$rawDate] ?? null),
                'precip' => $this->nullIfFill($precips[$rawDate] ?? null),
                'humidity' => $this->nullIfFill($humidities[$rawDate] ?? null),
                'wind_speed' => $this->nullIfFill($winds[$rawDate] ?? null),
                'solar_radiation' => $this->nullIfFill($solar[$rawDate] ?? null),
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
