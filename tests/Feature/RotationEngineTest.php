<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPriority;
use App\Models\Field;
use App\Models\NasaAgroData;
use App\Models\Observation;
use App\Models\Recommendation;
use App\Models\Rotation;
use App\Models\User;
use App\Services\RotationEngine;
use Database\Seeders\CropSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_al_menos_tres_estrategias_con_todos_los_campos(): void
    {
        $this->seed(CropSeeder::class);

        $field = $this->makeField();
        $this->seedPriorities($field);
        $this->seedObservations($field);
        $this->seedPower($field);

        $recommendations = app(RotationEngine::class)->generate($field);

        $this->assertGreaterThanOrEqual(3, $recommendations->count());
        $this->assertSame(
            $recommendations->count(),
            Recommendation::query()->where('field_id', $field->id)->count()
        );

        foreach ($recommendations as $recommendation) {
            $this->assertNotEmpty($recommendation->why_suggested);
            $this->assertNotEmpty($recommendation->evidence);
            $this->assertNotEmpty($recommendation->benefits);
            $this->assertNotEmpty($recommendation->risks);
            $this->assertNotEmpty($recommendation->information_gaps);
            $this->assertGreaterThanOrEqual(0.0, (float) $recommendation->confidence);
            $this->assertLessThanOrEqual(1.0, (float) $recommendation->confidence);
            $this->assertIsBool($recommendation->requires_local_validation);
            $this->assertNotNull($recommendation->rotation_id);
            $this->assertGreaterThan(0, $recommendation->rotation->steps->count());
        }
    }

    public function test_usa_evidencia_numerica_de_smap_gpm_y_power(): void
    {
        $this->seed(CropSeeder::class);

        $field = $this->makeField();
        $this->seedPriorities($field);
        $this->seedObservations($field);
        $this->seedPower($field);

        $recommendation = app(RotationEngine::class)->generate($field)->first();

        $this->assertStringContainsString('SMAP', $recommendation->evidence);
        $this->assertStringContainsString('GPM IMERG', $recommendation->evidence);
        $this->assertStringContainsString('NASA POWER', $recommendation->evidence);
        $this->assertStringContainsString('0,100', $recommendation->evidence);
    }

    public function test_regenerar_no_duplica_recomendaciones_ni_rotacion_actual(): void
    {
        $this->seed(CropSeeder::class);

        $field = $this->makeField();
        $this->seedPriorities($field);
        $this->seedObservations($field);

        $engine = app(RotationEngine::class);
        $first = $engine->generate($field);
        $second = $engine->generate($field);

        $this->assertSame($first->count(), $second->count());
        $this->assertSame(
            $second->count(),
            Recommendation::query()->where('field_id', $field->id)->count()
        );
        $this->assertSame(
            1,
            Rotation::query()->where('field_id', $field->id)->where('is_current', true)->count()
        );
    }

    public function test_falla_con_mensaje_util_si_no_hay_cultivos(): void
    {
        $field = $this->makeField();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CropSeeder');

        app(RotationEngine::class)->generate($field);
    }

    public function test_pesos_de_prioridades_se_normalizan_y_respetan_limites(): void
    {
        $field = $this->makeField();

        FarmerPriority::create([
            'field_id' => $field->id,
            'water_conservation_weight' => 99,
            'income_weight' => -5,
            'soil_health_weight' => 4,
            'erosion_weight' => 3,
            'market_weight' => 2,
        ]);

        $weights = app(RotationEngine::class)->weights($field->refresh());

        $this->assertSame(5, $weights['water_conservation_weight']);
        $this->assertSame(0, $weights['income_weight']);
        $this->assertSame(4, $weights['soil_health_weight']);
    }

    public function test_comparador_crea_parcela_y_genera_recomendaciones(): void
    {
        $this->seed(CropSeeder::class);

        $this->get('/rotaciones')
            ->assertOk()
            ->assertSee('Primero creá una parcela');

        $response = $this->post('/rotaciones/parcela', [
            'name' => 'Parcela Norte',
            'latitude' => -25.17,
            'longitude' => -57.48,
            'area_ha' => 8.5,
            'soil_type' => 'rojo',
            'soil_ph' => 6.2,
            'soil_organic_matter_pct' => 2.1,
            'irrigation_type' => 'secano',
        ]);

        $field = Field::query()->where('name', 'Parcela Norte')->firstOrFail();

        $response->assertRedirect(route('rotations.index', ['field_id' => $field->id]));

        $this->post('/rotaciones', ['field_id' => $field->id])
            ->assertRedirect(route('rotations.index', ['field_id' => $field->id]));

        $this->assertGreaterThanOrEqual(3, Recommendation::query()->where('field_id', $field->id)->count());

        $this->get('/rotaciones?field_id='.$field->id)
            ->assertOk()
            ->assertSee('Comparador de rotaciones')
            ->assertSee('Ahorro de agua');
    }

    private function makeField(): Field
    {
        $user = User::factory()->create();

        $farm = Farm::create([
            'user_id' => $user->id,
            'name' => 'Finca Test',
            'region' => 'Itapúa',
            'country' => 'Paraguay',
        ]);

        return Field::create([
            'farm_id' => $farm->id,
            'name' => 'Parcela 1',
            'latitude' => -25.17,
            'longitude' => -57.48,
            'area_ha' => 5.5,
            'soil_ph' => 6.1,
            'soil_organic_matter_pct' => 1.8,
            'irrigation_type' => 'secano',
        ]);
    }

    private function seedPriorities(Field $field): void
    {
        FarmerPriority::create([
            'field_id' => $field->id,
            'water_conservation_weight' => 5,
            'income_weight' => 3,
            'soil_health_weight' => 4,
            'erosion_weight' => 4,
            'market_weight' => 2,
        ]);
    }

    private function seedObservations(Field $field): void
    {
        Observation::create([
            'field_id' => $field->id,
            'source' => 'SMAP',
            'variable' => 'soil_moisture_am',
            'value' => 0.10,
            'unit' => 'm3/m3',
            'observed_at' => now()->subDays(2)->toDateString(),
            'raw_response' => ['task_id' => 'TEST'],
            'fetched_at' => now(),
        ]);

        foreach ([
            'precipitation' => 4.5,
            'precip_max_daily' => 62.0,
            'heavy_rain_days' => 2,
            'dry_streak_days' => 6,
        ] as $variable => $value) {
            Observation::create([
                'field_id' => $field->id,
                'source' => 'GPM',
                'variable' => $variable,
                'value' => $value,
                'unit' => $variable === 'heavy_rain_days' || $variable === 'dry_streak_days' ? 'días' : 'mm',
                'observed_at' => now()->subDays(3)->toDateString(),
                'raw_response' => ['granule_id' => 'GPM-TEST'],
                'fetched_at' => now(),
            ]);
        }
    }

    private function seedPower(Field $field): void
    {
        NasaAgroData::factory()->create([
            'lat' => $field->latitude,
            'lon' => $field->longitude,
            'date' => now()->subDays(5)->toDateString(),
            'temp' => 22.0,
            'temp_max' => 30.0,
            'temp_min' => 1.5,
            'precip' => 60.0,
            'humidity' => 65.0,
            'ndvi_value' => 0.42,
        ]);
    }
}
