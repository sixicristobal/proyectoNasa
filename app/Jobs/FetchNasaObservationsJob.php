<?php

namespace App\Jobs;

use App\Models\Field;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchNasaObservationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public ?int $fieldId = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}

    public function handle(): void
    {
        $fieldIds = $this->fieldId !== null
            ? collect([$this->fieldId])
            : Field::query()->orderBy('id')->pluck('id');

        if ($fieldIds->isEmpty()) {
            Log::info('[NASA] FetchNasaObservationsJob sin parcelas para procesar.');

            return;
        }

        foreach ($fieldIds as $fieldId) {
            FetchSmapJob::dispatch((int) $fieldId, $this->startDate, $this->endDate);
            FetchGpmJob::dispatch((int) $fieldId, $this->startDate, $this->endDate);
        }

        Log::info('[NASA] Jobs de observaciones encolados', [
            'fields' => $fieldIds->count(),
            'start' => $this->startDate,
            'end' => $this->endDate,
        ]);
    }
}
