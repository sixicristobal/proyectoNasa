<?php

namespace App\Services\Nasa;

use App\Models\Field;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmapService
{
    public function __construct(
        protected AppEarsService $appeears,
        protected CmrService $cmr,
    ) {}

    /**
     * Obtiene humedad de suelo SMAP para una parcela.
     *
     * Estrategia principal: AppEEARS point sample (CSV, sin manejar HDF5).
     * Fallback: búsqueda de gránulos CMR (solo metadatos, value = null) cuando
     * no hay token Earthdata o AppEEARS falla.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetch(Field $field, Carbon $start, Carbon $end): array
    {
        $product = (string) config('services.appeears.smap_product', 'SPL3SMP_E.006');
        $layer = (string) config('services.appeears.smap_layer', 'soil_moisture_am');

        if ($this->appeears->enabled()) {
            try {
                return $this->fetchViaAppEars($field, $start, $end, $product, $layer);
            } catch (Throwable $e) {
                Log::warning('[SMAP] AppEEARS falló, se usa CMR como fallback: '.$e->getMessage(), [
                    'field_id' => $field->id,
                ]);
            }
        }

        return $this->fetchGranuleMetadata($field, $start, $end, $layer);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchViaAppEars(
        Field $field,
        Carbon $start,
        Carbon $end,
        string $product,
        string $layer
    ): array {
        $taskName = "smap_field_{$field->id}_".now()->format('YmdHis');

        $taskId = $this->appeears->createPointTask(
            $taskName,
            [['product' => $product, 'layer' => $layer]],
            [[
                'id' => "field_{$field->id}",
                'latitude' => (float) $field->latitude,
                'longitude' => (float) $field->longitude,
            ]],
            $start,
            $end
        );

        $this->appeears->waitForTask(
            $taskId,
            (int) config('services.appeears.timeout', 1800),
            (int) config('services.appeears.poll', 20)
        );

        $files = $this->appeears->getBundle($taskId);

        $csv = collect($files)->first(function (array $file): bool {
            $type = strtolower((string) ($file['file_type'] ?? ''));
            $name = strtolower((string) ($file['file_name'] ?? ''));

            return $type === 'csv' || str_ends_with($name, '.csv');
        });

        if ($csv === null) {
            Log::warning('[SMAP] AppEEARS no devolvió CSV para la tarea '.$taskId);

            return [];
        }

        $localPath = $this->appeears->downloadFile(
            $taskId,
            (string) $csv['file_id'],
            isset($csv['file_name']) ? (string) $csv['file_name'] : null
        );

        $rows = $this->appeears->parsePointCsv($localPath, $layer);

        return collect($rows)->map(fn (array $row): array => [
            'source' => 'SMAP',
            'variable' => $layer,
            'value' => $row['value'],
            'unit' => 'm3/m3',
            'observed_at' => $row['date']->format('Y-m-d'),
            'raw_response' => [
                'task_id' => $taskId,
                'product' => $product,
                'layer' => $layer,
                'row' => $row['row'],
            ],
            'fetched_at' => now(),
        ])->values()->all();
    }

    /**
     * Fallback sin token: guarda evidencia de que existe el gránulo aunque
     * no se pueda extraer el valor HDF5 (queda como laguna de información).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchGranuleMetadata(Field $field, Carbon $start, Carbon $end, string $layer): array
    {
        $granules = $this->cmr->searchGranules(
            (string) config('services.appeears.smap_short_name', 'SPL3SMP_E'),
            (float) $field->latitude,
            (float) $field->longitude,
            $start,
            $end,
            10,
            ['version' => (string) config('services.appeears.smap_version', '061')]
        );

        return collect($granules)->map(function (array $granule) use ($layer): array {
            $timeStart = $granule['time_start'] ?? null;
            $date = is_string($timeStart) && $timeStart !== ''
                ? Carbon::parse($timeStart)->utc()->format('Y-m-d')
                : now()->format('Y-m-d');

            return [
                'source' => 'SMAP',
                'variable' => $layer,
                'value' => null,
                'unit' => 'm3/m3',
                'observed_at' => $date,
                'raw_response' => [
                    'granule_id' => $granule['id'] ?? null,
                    'title' => $granule['title'] ?? null,
                    'link' => $this->cmr->downloadableLink($granule),
                ],
                'fetched_at' => now(),
            ];
        })->values()->all();
    }
}
