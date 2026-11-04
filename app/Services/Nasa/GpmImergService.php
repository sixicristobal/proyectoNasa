<?php

namespace App\Services\Nasa;

use App\Models\Field;
use App\Services\EarthdataService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class GpmImergService
{
    public function __construct(
        protected CmrService $cmr,
        protected EarthdataService $earthdata,
    ) {}

    /**
     * Busca gránulos GPM IMERG y extrae precipitación diaria para la parcela.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetch(Field $field, Carbon $start, Carbon $end): array
    {
        $granules = $this->cmr->searchGranules(
            (string) config('services.gpm.short_name', 'GPM_3IMERGDF'),
            (float) $field->latitude,
            (float) $field->longitude,
            $start,
            $end,
            (int) config('services.gpm.page_size', 40),
            ['version' => (string) config('services.gpm.version', '07')]
        );

        $rows = [];
        $daily = [];

        foreach ($granules as $granule) {
            $date = $this->granuleDate($granule, $start)->format('Y-m-d');
            $link = $this->cmr->downloadableLink($granule);
            $value = null;

            if ($link !== null && $this->hasEarthdataToken()) {
                try {
                    $value = $this->downloadAndExtract(
                        $link,
                        (float) $field->latitude,
                        (float) $field->longitude
                    );
                } catch (Throwable $e) {
                    Log::warning('[GPM] No se pudo extraer precipitación del gránulo: '.$e->getMessage(), [
                        'field_id' => $field->id,
                        'granule' => $granule['id'] ?? null,
                    ]);
                }
            }

            $rows[] = [
                'source' => 'GPM',
                'variable' => 'precipitation',
                'value' => $value,
                'unit' => 'mm',
                'observed_at' => $date,
                'raw_response' => [
                    'granule_id' => $granule['id'] ?? null,
                    'title' => $granule['title'] ?? null,
                    'link' => $link,
                ],
                'fetched_at' => now(),
            ];

            if ($value !== null) {
                $daily[$date] = $value;
            }
        }

        $lastDate = $this->lastDate($daily, $end);

        foreach ($this->metrics($daily) as $variable => $value) {
            $rows[] = [
                'source' => 'GPM',
                'variable' => $variable,
                'value' => $value,
                'unit' => $variable === 'heavy_rain_days' || $variable === 'dry_streak_days' ? 'días' : 'mm',
                'observed_at' => $lastDate,
                'raw_response' => [
                    'derived_from' => array_keys($daily),
                    'window' => [$start->format('Y-m-d'), $end->format('Y-m-d')],
                ],
                'fetched_at' => now(),
            ];
        }

        return $rows;
    }

    /**
     * Métricas derivables de la serie diaria de precipitación.
     *
     * @param  array<string, float>  $dailyPrecip
     * @return array<string, float|int>
     */
    public function metrics(array $dailyPrecip): array
    {
        $values = array_values(array_filter(
            $dailyPrecip,
            fn ($value) => $value !== null && is_numeric($value)
        ));

        if ($values === []) {
            return [];
        }

        $streak = 0;
        $maxStreak = 0;

        foreach ($dailyPrecip as $value) {
            if ($value !== null && (float) $value < 1.0) {
                $streak++;
                $maxStreak = max($maxStreak, $streak);
            } else {
                $streak = 0;
            }
        }

        return [
            'precip_max_daily' => (float) max($values),
            'heavy_rain_days' => count(array_filter($values, fn ($value) => (float) $value > 50.0)),
            'dry_streak_days' => $maxStreak,
        ];
    }

    protected function downloadAndExtract(string $url, float $lat, float $lon): ?float
    {
        $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'gpm_'.time().'.HDF5');
        $localPath = $this->earthdata->downloadFile($url, $filename);

        $script = base_path('storage/scripts/extract_gpm_precip.py');

        if (! is_file($script)) {
            throw new \RuntimeException("No existe el script {$script}.");
        }

        $result = Process::timeout(180)->run([
            'python3',
            $script,
            $localPath,
            (string) $lat,
            (string) $lon,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('extract_gpm_precip.py falló: '.$result->errorOutput());
        }

        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            throw new \RuntimeException('Salida inválida de extract_gpm_precip.py: '.$result->output());
        }

        if (isset($payload['error']) && $payload['error'] !== null) {
            throw new \RuntimeException('extract_gpm_precip.py: '.$payload['error']);
        }

        return isset($payload['precip_mm']) && $payload['precip_mm'] !== null
            ? (float) $payload['precip_mm']
            : null;
    }

    /**
     * @param  array<string, mixed>  $granule
     */
    protected function granuleDate(array $granule, Carbon $fallback): Carbon
    {
        $timeStart = $granule['time_start'] ?? null;

        if (is_string($timeStart) && $timeStart !== '') {
            return Carbon::parse($timeStart)->utc();
        }

        return $fallback->copy()->startOfDay();
    }

    /**
     * @param  array<string, float>  $daily
     */
    protected function lastDate(array $daily, Carbon $fallback): string
    {
        if ($daily === []) {
            return $fallback->copy()->format('Y-m-d');
        }

        $dates = array_keys($daily);
        sort($dates);

        return (string) end($dates);
    }

    protected function hasEarthdataToken(): bool
    {
        return trim((string) config('services.earthdata.token')) !== '';
    }
}
