<?php

namespace App\Services;

use App\Models\Crop;
use App\Models\Field;
use App\Models\NasaAgroData;
use App\Models\Recommendation;
use App\Models\Rotation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class RotationEngine
{
    public const DEFAULT_WEIGHTS = [
        'water_conservation_weight' => 3,
        'income_weight' => 3,
        'soil_health_weight' => 3,
        'erosion_weight' => 3,
        'market_weight' => 3,
    ];

    /**
     * Genera y persiste al menos 3 estrategias de rotación para una parcela.
     *
     * @return Collection<int, Recommendation>
     */
    public function generate(Field $field, ?Carbon $now = null): Collection
    {
        $now ??= now();

        $crops = Crop::query()->orderBy('name')->get();

        if ($crops->isEmpty()) {
            throw new RuntimeException(
                'El catálogo de cultivos está vacío. Ejecutá: php artisan db:seed --class=CropSeeder'
            );
        }

        $weights = $this->weights($field);
        $context = $this->context($field, $now);

        $strategies = collect([
            $this->currentStrategy($field, $crops),
            $this->waterSavingStrategy($crops),
            $this->soilHealthStrategy($crops),
            $this->incomeStrategy($crops),
        ]);

        $strategies = $this->ensureDistinct($strategies, $crops);

        $scored = $strategies
            ->map(function (array $strategy) use ($weights, $context): array {
                return array_merge($strategy, $this->scoreRotation($strategy['crops'], $weights, $context));
            })
            ->sortByDesc('score')
            ->values();

        $this->persist($field, $scored, $context, $weights);

        return $field->recommendations()
            ->with('rotation.steps.crop')
            ->orderBy('rank')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function weights(Field $field): array
    {
        $priorities = $field->priorities;
        $weights = [];

        foreach (self::DEFAULT_WEIGHTS as $key => $default) {
            $value = $priorities !== null ? (int) ($priorities->{$key} ?? $default) : $default;
            $weights[$key] = max(0, min(5, $value));
        }

        return array_sum($weights) > 0 ? $weights : self::DEFAULT_WEIGHTS;
    }

    /**
     * Score 0..1 por dimensión de un cultivo individual.
     */
    public function cropScore(Crop $crop, string $dimension): float
    {
        $water = ['bajo' => 1.0, 'medio' => 0.6, 'alto' => 0.25][$crop->water_demand] ?? 0.5;
        $market = ['bajo' => 0.3, 'medio' => 0.6, 'alto' => 1.0][$crop->market_value] ?? 0.5;
        $erosion = ['bajo' => 0.3, 'medio' => 0.6, 'alto' => 1.0][$crop->erosion_control] ?? 0.5;

        $score = match ($dimension) {
            'water' => $water
                + ($crop->drought_tolerant ? 0.15 : 0)
                + ($crop->deep_root ? 0.10 : 0),
            'income', 'market' => $market,
            'soil' => ($crop->nitrogen_fixing ? 0.45 : 0.10)
                + ($crop->deep_root ? 0.15 : 0)
                + ($crop->water_demand !== 'alto' ? 0.20 : 0)
                + ($crop->erosion_control === 'alto' ? 0.20 : 0.10),
            'erosion' => $erosion
                + ($crop->deep_root ? 0.15 : 0)
                + ($crop->water_demand === 'bajo' ? 0.05 : 0),
            default => 0.5,
        };

        return round(max(0.0, min(1.0, $score)), 4);
    }

    /**
     * Score multiobjetivo ponderado por prioridades del agricultor.
     *
     * @param  array<int, Crop>  $crops
     * @param  array<string, int>  $weights
     * @param  array<string, mixed>  $context
     * @return array{score: float, dimensions: array<string, float>}
     */
    public function scoreRotation(array $crops, array $weights, array $context): array
    {
        $count = max(1, count($crops));
        $dimensions = ['water' => 0.0, 'income' => 0.0, 'soil' => 0.0, 'erosion' => 0.0, 'market' => 0.0];

        foreach ($crops as $crop) {
            foreach (array_keys($dimensions) as $dimension) {
                $dimensions[$dimension] += $this->cropScore($crop, $dimension);
            }
        }

        foreach ($dimensions as $dimension => $value) {
            $dimensions[$dimension] = $value / $count;
        }

        $highWater = count(array_filter(
            $crops,
            fn (Crop $crop): bool => $crop->water_demand === 'alto'
        )) / $count;

        if ($context['soil_moisture_low']) {
            $dimensions['water'] = max(0.0, $dimensions['water'] - 0.15 * $highWater);
        }

        $lowErosion = count(array_filter(
            $crops,
            fn (Crop $crop): bool => $crop->erosion_control === 'bajo'
        )) / $count;

        if ($context['heavy_rain']) {
            $dimensions['erosion'] = max(0.0, $dimensions['erosion'] - 0.15 * $lowErosion);
        }

        $uniqueCrops = count(array_unique(array_map(fn (Crop $crop): int => $crop->id, $crops)));
        $families = count(array_unique(array_map(fn (Crop $crop): string => (string) $crop->family, $crops)));
        $diversity = min(1.0, (($uniqueCrops / $count) * 0.6) + (($families / $count) * 0.4));
        $dimensions['soil'] = min(1.0, $dimensions['soil'] + 0.08 * $diversity);

        $weightMap = [
            'water' => $weights['water_conservation_weight'],
            'income' => $weights['income_weight'],
            'soil' => $weights['soil_health_weight'],
            'erosion' => $weights['erosion_weight'],
            'market' => $weights['market_weight'],
        ];

        $totalWeight = array_sum($weightMap);
        $weighted = 0.0;

        foreach ($weightMap as $dimension => $weight) {
            $weighted += $dimensions[$dimension] * $weight;
        }

        $weighted = $totalWeight > 0 ? $weighted / $totalWeight : 0.0;

        return [
            'score' => round(max(0.0, min(1.0, $weighted)) * 100, 1),
            'dimensions' => $dimensions,
        ];
    }

    /**
     * @param  Collection<int, Crop>  $crops
     * @return array<string, mixed>
     */
    protected function currentStrategy(Field $field, Collection $crops): array
    {
        $current = $field->rotations()
            ->where('is_current', true)
            ->with('steps.crop')
            ->first();

        if ($current !== null) {
            $currentCrops = $current->steps->pluck('crop')->filter()->values()->all();

            if ($currentCrops !== []) {
                return [
                    'key' => 'actual',
                    'name' => $current->name,
                    'description' => $current->description ?? 'Rotación actual registrada por el productor.',
                    'crops' => $currentCrops,
                    'rotation' => $current,
                    'strategy_type' => 'actual',
                    'assumed' => false,
                ];
            }
        }

        $baseline = $crops
            ->sortByDesc(fn (Crop $crop): float => $this->cropScore($crop, 'income'))
            ->first();

        return [
            'key' => 'actual',
            'name' => 'Rotación actual (monocultivo de '.$baseline->name.')',
            'description' => 'Línea base asumida porque no hay rotación actual registrada para la parcela.',
            'crops' => [$baseline],
            'rotation' => null,
            'strategy_type' => 'actual',
            'assumed' => true,
        ];
    }

    /**
     * @param  Collection<int, Crop>  $crops
     * @return array<string, mixed>
     */
    protected function waterSavingStrategy(Collection $crops): array
    {
        return $this->makeStrategy(
            'Ahorro de agua y resiliencia climática',
            'Prioriza cultivos de bajo requerimiento hídrico, tolerantes a sequía y con raíz profunda.',
            $this->pick($crops, [
                fn (Crop $crop): float => $this->cropScore($crop, 'water'),
                fn (Crop $crop): float => $this->cropScore($crop, 'soil'),
                fn (Crop $crop): float => $this->cropScore($crop, 'erosion'),
            ])
        );
    }

    /**
     * @param  Collection<int, Crop>  $crops
     * @return array<string, mixed>
     */
    protected function soilHealthStrategy(Collection $crops): array
    {
        return $this->makeStrategy(
            'Salud del suelo y fertilidad',
            'Combina leguminosas fijadoras de nitrógeno con cultivos de renta y cobertura.',
            $this->pick($crops, [
                fn (Crop $crop): float => $this->cropScore($crop, 'soil'),
                fn (Crop $crop): float => $this->cropScore($crop, 'income'),
                fn (Crop $crop): float => $this->cropScore($crop, 'erosion'),
            ])
        );
    }

    /**
     * @param  Collection<int, Crop>  $crops
     * @return array<string, mixed>
     */
    protected function incomeStrategy(Collection $crops): array
    {
        return $this->makeStrategy(
            'Ingreso diversificado y cobertura',
            'Diversifica el ingreso y mantiene cobertura del suelo para reducir erosión.',
            $this->pick($crops, [
                fn (Crop $crop): float => $this->cropScore($crop, 'income'),
                fn (Crop $crop): float => $this->cropScore($crop, 'erosion'),
                fn (Crop $crop): float => $this->cropScore($crop, 'soil'),
            ])
        );
    }

    /**
     * @param  Collection<int, Crop>  $crops
     * @param  array<int, callable(Crop): float>  $criteria
     * @return array<int, Crop>
     */
    protected function pick(Collection $crops, array $criteria): array
    {
        $picked = [];
        $usedIds = [];

        foreach ($criteria as $criterion) {
            $available = $crops
                ->reject(fn (Crop $crop): bool => in_array($crop->id, $usedIds, true))
                ->sortByDesc($criterion)
                ->values();

            $crop = $available->first() ?? $crops->sortByDesc($criterion)->first();

            if ($crop !== null) {
                $picked[] = $crop;
                $usedIds[] = $crop->id;
            }
        }

        return $picked;
    }

    /**
     * @param  array<int, Crop>  $crops
     * @return array<string, mixed>
     */
    protected function makeStrategy(string $name, string $description, array $crops): array
    {
        return [
            'key' => (string) str($name)->slug(),
            'name' => $name,
            'description' => $description,
            'crops' => array_values($crops),
            'rotation' => null,
            'strategy_type' => 'propuesta_ia',
            'assumed' => false,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $strategies
     * @param  Collection<int, Crop>  $crops
     * @return Collection<int, array<string, mixed>>
     */
    protected function ensureDistinct(Collection $strategies, Collection $crops): Collection
    {
        $unique = $strategies
            ->unique(fn (array $strategy): string => $this->signature($strategy['crops']))
            ->values();

        $attempts = 0;

        while ($unique->count() < 3 && $attempts < 6) {
            $fallback = $crops->take(3)->values()->all();

            if ($attempts % 2 === 1) {
                $fallback = array_reverse($fallback);
            }

            $unique->push($this->makeStrategy(
                'Rotación diversificada '.($unique->count() + 1),
                'Alternativa generada para garantizar al menos tres estrategias comparables.',
                $fallback
            ));

            $attempts++;
        }

        return $unique->take(6)->values();
    }

    /**
     * @param  array<int, Crop>  $crops
     */
    protected function signature(array $crops): string
    {
        return collect($crops)->pluck('id')->implode('-');
    }

    /**
     * @return array<string, mixed>
     */
    protected function context(Field $field, Carbon $now): array
    {
        $from = $now->copy()->subDays(90)->startOfDay();

        $observations = $field->observations()
            ->where('observed_at', '>=', $from->toDateString())
            ->get();

        $smap = $observations
            ->where('source', 'SMAP')
            ->whereIn('variable', ['soil_moisture_am', 'soil_moisture_pm'])
            ->whereNotNull('value');

        $gpm = $observations->where('source', 'GPM');

        $gpmValue = function (string $variable) use ($gpm): ?float {
            $observation = $gpm
                ->where('variable', $variable)
                ->whereNotNull('value')
                ->sortByDesc('observed_at')
                ->first();

            return $observation?->value;
        };

        $powerRows = NasaAgroData::query()
            ->near((float) $field->latitude, (float) $field->longitude)
            ->where('date', '>=', $from->toDateString())
            ->get();

        $tempMinRows = $powerRows->whereNotNull('temp_min');
        $smapMean = $smap->isNotEmpty() ? round((float) $smap->avg('value'), 4) : null;
        $powerTempMin = $tempMinRows->isNotEmpty() ? (float) $tempMinRows->min('temp_min') : null;

        return [
            'smap_mean' => $smapMean,
            'smap_count' => $smap->count(),
            'soil_moisture_low' => $smapMean !== null && $smapMean < 0.15,
            'gpm_count' => $gpm->count(),
            'gpm_max_daily' => $gpmValue('precip_max_daily'),
            'gpm_heavy_rain_days' => $gpmValue('heavy_rain_days'),
            'gpm_dry_streak' => $gpmValue('dry_streak_days'),
            'heavy_rain' => (float) ($gpmValue('heavy_rain_days') ?? 0) > 0,
            'power_count' => $powerRows->count(),
            'power_temp_min' => $powerTempMin,
            'power_precip_sum' => $powerRows->whereNotNull('precip')->isNotEmpty()
                ? round((float) $powerRows->whereNotNull('precip')->sum('precip'), 1)
                : null,
            'ndvi_mean' => $powerRows->whereNotNull('ndvi_value')->isNotEmpty()
                ? round((float) $powerRows->whereNotNull('ndvi_value')->avg('ndvi_value'), 3)
                : null,
            'frost_risk' => $powerTempMin !== null && $powerTempMin < 3.0,
        ];
    }

    /**
     * @param  array<int, Crop>  $crops
     * @param  array<string, int>  $weights
     * @param  array<string, mixed>  $context
     */
    protected function why(array $strategy, array $weights, array $context): string
    {
        $labels = [
            'water_conservation_weight' => 'conservación de agua',
            'income_weight' => 'ingresos',
            'soil_health_weight' => 'salud del suelo',
            'erosion_weight' => 'control de erosión',
            'market_weight' => 'mercado',
        ];

        arsort($weights);
        $top = array_slice($weights, 0, 3, true);
        $priorityText = collect($top)
            ->map(fn (int $weight, string $key): string => "{$labels[$key]} (peso {$weight})")
            ->implode(', ');

        $cropNames = collect($strategy['crops'])->pluck('name')->implode(' → ');
        $extra = '';

        if ($context['soil_moisture_low']) {
            $extra .= ' Además, SMAP indica humedad baja ('.$this->fmt($context['smap_mean'], 3).' m³/m³).';
        }

        if ($context['heavy_rain']) {
            $extra .= ' GPM IMERG registra días de lluvia intensa, por eso se prioriza la cobertura del suelo.';
        }

        return "Se propone {$cropNames} porque tus prioridades ponderan {$priorityText}.{$extra}";
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function evidence(array $context): string
    {
        $lines = [];

        if ($context['smap_count'] > 0) {
            $lines[] = sprintf(
                'SMAP: humedad media %s m³/m³ en 90 días (%d observaciones).',
                $this->fmt($context['smap_mean'], 3),
                (int) $context['smap_count']
            );
        } else {
            $lines[] = 'SMAP: sin observaciones en los últimos 90 días.';
        }

        if ($context['gpm_count'] > 0) {
            $parts = [];

            if ($context['gpm_max_daily'] !== null) {
                $parts[] = 'máx. diario '.$this->fmt($context['gpm_max_daily']).' mm';
            }

            if ($context['gpm_heavy_rain_days'] !== null) {
                $parts[] = $this->fmt($context['gpm_heavy_rain_days'], 0).' días con lluvia >50 mm';
            }

            if ($context['gpm_dry_streak'] !== null) {
                $parts[] = 'racha seca de '.$this->fmt($context['gpm_dry_streak'], 0).' días';
            }

            $lines[] = 'GPM IMERG: '.(($parts !== []) ? implode(', ', $parts) : 'gránulos disponibles sin valor extraído').'.';
        } else {
            $lines[] = 'GPM IMERG: sin observaciones en los últimos 90 días.';
        }

        if ($context['power_count'] > 0) {
            $parts = [];

            if ($context['power_temp_min'] !== null) {
                $parts[] = 'Tmin '.$this->fmt($context['power_temp_min']).' °C';
            }

            if ($context['power_precip_sum'] !== null) {
                $parts[] = 'lluvia acumulada '.$this->fmt($context['power_precip_sum']).' mm';
            }

            $lines[] = 'NASA POWER: '.(($parts !== []) ? implode(', ', $parts) : 'datos disponibles').'.';
        } else {
            $lines[] = 'NASA POWER: sin datos para las coordenadas de la parcela.';
        }

        if ($context['ndvi_mean'] !== null) {
            $lines[] = 'NDVI promedio: '.$this->fmt($context['ndvi_mean'], 2).'.';
        }

        return implode(' ', $lines);
    }

    /**
     * @param  array<int, Crop>  $crops
     */
    protected function benefits(array $crops): string
    {
        $benefits = [];

        foreach ($crops as $crop) {
            if ($crop->nitrogen_fixing) {
                $benefits[] = "{$crop->name}: fija nitrógeno y reduce la necesidad de fertilizante.";
            }

            if ($crop->drought_tolerant || $crop->water_demand === 'bajo') {
                $benefits[] = "{$crop->name}: bajo requerimiento hídrico o tolerancia a sequía.";
            }

            if ($crop->erosion_control === 'alto') {
                $benefits[] = "{$crop->name}: buena cobertura y control de erosión.";
            }

            if ($crop->deep_root) {
                $benefits[] = "{$crop->name}: raíz profunda que mejora la infiltración.";
            }

            if ($crop->market_value === 'alto') {
                $benefits[] = "{$crop->name}: alto valor de mercado.";
            }

            if ($crop->forage_value === 'alto') {
                $benefits[] = "{$crop->name}: aporta forraje para diversificar el ingreso.";
            }
        }

        $benefits = array_values(array_unique($benefits));

        return $benefits !== []
            ? implode(' ', $benefits)
            : 'La rotación diversifica cultivos y reduce la dependencia de un solo mercado.';
    }

    /**
     * @param  array<int, Crop>  $crops
     * @param  array<string, mixed>  $context
     */
    protected function risks(array $crops, array $context): string
    {
        $risks = [];

        foreach ($crops as $crop) {
            if ($crop->water_demand === 'alto') {
                $risks[] = "{$crop->name} tiene demanda hídrica alta; si la humedad sigue baja puede perder rendimiento.";
            }

            if ($context['heavy_rain'] && $crop->erosion_control === 'bajo') {
                $risks[] = "{$crop->name} deja poco rastrojo y con lluvias intensas aumenta el riesgo de erosión.";
            }
        }

        if ($context['frost_risk']) {
            $risks[] = 'NASA POWER registra Tmin menor a 3 °C; el catálogo no modela tolerancia a heladas, validar fechas de siembra.';
        }

        if ($context['smap_count'] === 0) {
            $risks[] = 'Sin SMAP reciente, el balance hídrico de la rotación es incierto.';
        }

        $risks = array_values(array_unique($risks));

        return $risks !== []
            ? implode(' ', $risks)
            : 'Sin riesgos críticos con los datos disponibles; igualmente requiere validación local.';
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $strategy
     */
    protected function gaps(Field $field, array $context, array $strategy): string
    {
        $gaps = [];

        if ($context['smap_count'] === 0) {
            $gaps[] = 'Sin observaciones SMAP en 90 días.';
        }

        if ($context['gpm_count'] === 0) {
            $gaps[] = 'Sin observaciones GPM IMERG en 90 días.';
        }

        if ($context['power_count'] === 0) {
            $gaps[] = 'Sin datos NASA POWER para las coordenadas de la parcela.';
        }

        if ($field->soil_ph === null || $field->soil_organic_matter_pct === null) {
            $gaps[] = 'Falta análisis de suelo (pH y materia orgánica).';
        }

        if ($strategy['assumed']) {
            $gaps[] = 'No se registró la rotación actual; se asumió un monocultivo como línea base.';
        }

        $gaps[] = 'No se modelan precios, heladas ni malezas específicas; validar con el productor.';

        return implode(' ', $gaps);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $strategy
     */
    protected function confidence(array $context, float $score, bool $assumed): float
    {
        $sources = 0;

        if ($context['smap_count'] > 0) {
            $sources++;
        }

        if ($context['gpm_count'] > 0) {
            $sources++;
        }

        if ($context['power_count'] > 0) {
            $sources++;
        }

        $completeness = $sources / 3;
        $confidence = 0.30 + 0.50 * $completeness + 0.15 * ($score / 100);

        if ($assumed) {
            $confidence -= 0.05;
        }

        return round(max(0.10, min(0.95, $confidence)), 2);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $strategy
     */
    protected function requiresValidation(Field $field, array $context, array $strategy): bool
    {
        return $strategy['assumed']
            || $context['smap_count'] === 0
            || $context['gpm_count'] === 0
            || $field->soil_ph === null
            || $field->soil_organic_matter_pct === null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scored
     * @param  array<string, mixed>  $context
     * @param  array<string, int>  $weights
     */
    protected function persist(Field $field, Collection $scored, array $context, array $weights): void
    {
        $ranks = [];
        $proposedNames = [];

        foreach ($scored as $index => $strategy) {
            $rank = $index + 1;
            $ranks[] = $rank;
            $rotation = $strategy['rotation'] ?? null;

            if ($strategy['strategy_type'] === 'actual') {
                if ($rotation === null) {
                    $rotation = $field->rotations()->where('is_current', true)->first();

                    if ($rotation === null) {
                        $rotation = Rotation::create([
                            'field_id' => $field->id,
                            'name' => $strategy['name'],
                            'is_current' => true,
                            'description' => $strategy['description'],
                            'strategy_type' => 'actual',
                        ]);

                        $this->syncSteps($rotation, $strategy['crops']);
                    }
                }
            } else {
                $proposedNames[] = $strategy['name'];

                $rotation = Rotation::updateOrCreate(
                    [
                        'field_id' => $field->id,
                        'name' => $strategy['name'],
                        'strategy_type' => 'propuesta_ia',
                    ],
                    [
                        'description' => $strategy['description'],
                        'is_current' => false,
                    ]
                );

                $rotation->steps()->delete();
                $this->syncSteps($rotation, $strategy['crops']);
            }

            Recommendation::updateOrCreate(
                ['field_id' => $field->id, 'rank' => $rank],
                [
                    'rotation_id' => $rotation?->id,
                    'why_suggested' => $this->why($strategy, $weights, $context),
                    'evidence' => $this->evidence($context),
                    'benefits' => $this->benefits($strategy['crops']),
                    'risks' => $this->risks($strategy['crops'], $context),
                    'information_gaps' => $this->gaps($field, $context, $strategy),
                    'confidence' => $this->confidence($context, (float) $strategy['score'], (bool) $strategy['assumed']),
                    'requires_local_validation' => $this->requiresValidation($field, $context, $strategy),
                ]
            );
        }

        Recommendation::query()
            ->where('field_id', $field->id)
            ->whereNotIn('rank', $ranks)
            ->delete();

        if ($proposedNames !== []) {
            $field->rotations()
                ->where('strategy_type', 'propuesta_ia')
                ->whereNotIn('name', $proposedNames)
                ->delete();
        }
    }

    /**
     * @param  array<int, Crop>  $crops
     */
    protected function syncSteps(Rotation $rotation, array $crops): void
    {
        $seasons = ['verano', 'invierno', 'verano'];

        foreach ($crops as $index => $crop) {
            $rotation->steps()->create([
                'crop_id' => $crop->id,
                'sequence' => $index + 1,
                'season' => $seasons[$index] ?? 'todo_año',
                'duration_days' => $crop->growing_days ?? 90,
                'notes' => null,
            ]);
        }
    }

    protected function fmt(mixed $value, int $decimals = 1): string
    {
        if ($value === null || ! is_numeric($value)) {
            return 'sin dato';
        }

        return number_format((float) $value, $decimals, ',', '.');
    }
}
