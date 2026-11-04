<?php

namespace App\Jobs;

use App\Models\Field;
use App\Models\Observation;
use App\Services\Nasa\GpmImergService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchGpmJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1900;

    public function __construct(
        public int $fieldId,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}

    public function handle(GpmImergService $gpm): void
    {
        $field = Field::query()->find($this->fieldId);

        if ($field === null) {
            Log::warning('[GPM] Parcela no encontrada', ['field_id' => $this->fieldId]);

            return;
        }

        [$start, $end] = $this->range();

        $rows = Cache::remember(
            "obs:{$field->id}:GPM:{$start->toDateString()}:{$end->toDateString()}",
            now()->addHours(24),
            fn (): array => $gpm->fetch($field, $start, $end)
        );

        $count = Observation::upsertRows(array_map(
            fn (array $row): array => $row + [
                'field_id' => $field->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $rows
        ));

        Log::info('[GPM] Observaciones persistidas', [
            'field_id' => $field->id,
            'rows' => $count,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[GPM] Job falló', [
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
            : $end->copy()->subDays(30)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }
}
