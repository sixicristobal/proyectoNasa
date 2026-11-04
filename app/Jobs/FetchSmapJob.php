<?php

namespace App\Jobs;

use App\Models\Field;
use App\Models\Observation;
use App\Services\Nasa\SmapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchSmapJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1900;

    public function __construct(
        public int $fieldId,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}

    public function handle(SmapService $smap): void
    {
        $field = Field::query()->find($this->fieldId);

        if ($field === null) {
            Log::warning('[SMAP] Parcela no encontrada', ['field_id' => $this->fieldId]);

            return;
        }

        [$start, $end] = $this->range();

        $rows = Cache::remember(
            "obs:{$field->id}:SMAP:{$start->toDateString()}:{$end->toDateString()}",
            now()->addHours(24),
            fn (): array => $smap->fetch($field, $start, $end)
        );

        $count = Observation::upsertRows(array_map(
            fn (array $row): array => $row + [
                'field_id' => $field->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $rows
        ));

        Log::info('[SMAP] Observaciones persistidas', [
            'field_id' => $field->id,
            'rows' => $count,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[SMAP] Job falló', [
            'field_id' => $this->fieldId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(): array
    {
        $end = $this->endDate !== null
            ? Carbon::parse($this->endDate)->endOfDay()
            : now()->endOfDay();

        $start = $this->startDate !== null
            ? Carbon::parse($this->startDate)->startOfDay()
            : $end->copy()->subDays(90)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }
}
