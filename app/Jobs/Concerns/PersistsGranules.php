<?php

namespace App\Jobs\Concerns;

use App\Models\Field;
use App\Models\Observation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

trait PersistsGranules
{
    /**
     * Guarda en `observations` los gránulos devueltos por NASA CMR.
     *
     * Cada gránulo se identifica con una variable estable (hash del id) para
     * que el upsert sea idempotente entre ejecuciones del job.
     *
     * @param  array<int, array<string, mixed>>  $granules
     */
    protected function persistGranules(Field $field, string $source, array $granules): int
    {
        $rows = [];

        foreach ($granules as $granule) {
            if (! is_array($granule)) {
                continue;
            }

            $granuleId = (string) ($granule['id'] ?? $granule['producer_granule_id'] ?? '');
            $hashSeed = $granuleId !== '' ? $granuleId : (string) json_encode($granule);

            $rows[] = [
                'field_id' => $field->id,
                'source' => $source,
                'variable' => 'granule_'.substr(sha1($hashSeed), 0, 8),
                'value' => null,
                'unit' => 'granule',
                'observed_at' => $this->granuleObservedAt($granule),
                'raw_response' => $granule,
                'fetched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $count = Observation::upsertRows($rows);

        Log::info('[NASA CMR] Gránulos persistidos', [
            'field_id' => $field->id,
            'source' => $source,
            'rows' => $count,
        ]);

        return $count;
    }

    /**
     * @param  array<string, mixed>  $granule
     */
    protected function granuleObservedAt(array $granule): string
    {
        $timeStart = $granule['time_start'] ?? null;

        try {
            return $timeStart !== null
                ? Carbon::parse((string) $timeStart)->toDateString()
                : now()->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }
}
