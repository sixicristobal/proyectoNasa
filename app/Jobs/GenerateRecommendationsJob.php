<?php

namespace App\Jobs;

use App\Models\Field;
use App\Services\RotationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateRecommendationsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public ?int $fieldId = null) {}

    public function handle(RotationEngine $engine): void
    {
        $fields = $this->fieldId !== null
            ? Field::query()->whereKey($this->fieldId)->get()
            : Field::query()->orderBy('id')->get();

        if ($fields->isEmpty()) {
            Log::info('[RotationEngine] Sin parcelas para generar recomendaciones.');

            return;
        }

        foreach ($fields as $field) {
            try {
                $recommendations = $engine->generate($field);

                Log::info('[RotationEngine] Recomendaciones generadas', [
                    'field_id' => $field->id,
                    'count' => $recommendations->count(),
                ]);
            } catch (Throwable $e) {
                report($e);

                Log::warning('[RotationEngine] No se pudo generar para la parcela '.$field->id.': '.$e->getMessage());
            }
        }
    }
}
