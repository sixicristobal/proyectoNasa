<?php

namespace Tests\Feature;

use App\Jobs\FetchGpmJob;
use App\Jobs\FetchSmapJob;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Observation;
use App\Models\User;
use App\Services\Nasa\AppEarsService;
use App\Services\Nasa\CmrService;
use App\Services\Nasa\GpmImergService;
use App\Services\Nasa\SmapService;
use App\Services\NasaImageryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NasaPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_cmr_service_arma_la_consulta_correcta(): void
    {
        Http::fake([
            'cmr.earthdata.nasa.gov/*' => Http::response([
                'feed' => ['entry' => [['id' => 'G1', 'title' => 'gránulo']]],
            ]),
        ]);

        $entries = app(CmrService::class)->searchGranules(
            'GPM_3IMERGDF',
            -25.17,
            -57.48,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-09-23'),
            5
        );

        $this->assertSame('G1', $entries[0]['id']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'granules.json')
                && $request['short_name'] === 'GPM_3IMERGDF'
                && $request['point'] === '-57.48,-25.17'
                && (int) $request['page_size'] === 5
                && str_contains((string) $request['temporal'], '2026-08-01');
        });
    }

    public function test_cmr_service_rechaza_coordenadas_invalidas(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(CmrService::class)->searchGranules(
            'SPL3SMP_E',
            120,
            -57.48,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-09-23')
        );
    }

    public function test_appeears_parsea_csv_point_sample(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'appeears_');
        file_put_contents($path, implode("\n", [
            'ID,Latitude,Longitude,Date,SPL3SMP_E_006_soil_moisture_am',
            'field_1,-25.17,-57.48,2026-08-01,0.1234',
            'field_1,-25.17,-57.48,2026-08-02,NaN',
        ]));

        try {
            $rows = app(AppEarsService::class)->parsePointCsv($path, 'soil_moisture_am');
        } finally {
            @unlink($path);
        }

        $this->assertCount(2, $rows);
        $this->assertSame('2026-08-01', $rows[0]['date']->format('Y-m-d'));
        $this->assertSame(0.1234, $rows[0]['value']);
        $this->assertNull($rows[1]['value']);
    }

    public function test_gpm_metrics_deriva_intensidad_lluvia_fuerte_y_racha_seca(): void
    {
        $metrics = app(GpmImergService::class)->metrics([
            '2026-08-01' => 60.0,
            '2026-08-02' => 0.5,
            '2026-08-03' => 0.2,
            '2026-08-04' => 10.0,
        ]);

        $this->assertSame(60.0, $metrics['precip_max_daily']);
        $this->assertSame(1, $metrics['heavy_rain_days']);
        $this->assertSame(2, $metrics['dry_streak_days']);
    }

    public function test_observation_upsert_es_idempotente_y_castea_json(): void
    {
        $field = $this->makeField();

        $row = [
            'field_id' => $field->id,
            'source' => 'SMAP',
            'variable' => 'soil_moisture_am',
            'value' => 0.12,
            'unit' => 'm3/m3',
            'observed_at' => '2026-08-01',
            'raw_response' => ['task_id' => 'ABC'],
            'fetched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Observation::upsertRows([$row]);
        Observation::upsertRows([array_merge($row, ['value' => 0.22])]);

        $this->assertSame(1, Observation::count());
        $observation = Observation::first();
        $this->assertSame(0.22, $observation->value);
        $this->assertSame('ABC', $observation->raw_response['task_id']);
    }

    public function test_fetch_smap_job_persiste_fallback_cmr_sin_token(): void
    {
        config(['services.earthdata.token' => '']);

        Http::fake([
            'cmr.earthdata.nasa.gov/*' => Http::response([
                'feed' => ['entry' => [[
                    'id' => 'SMAP-G1',
                    'title' => 'SPL3SMP_E gránulo',
                    'time_start' => '2026-08-01T00:00:00.000Z',
                    'links' => [],
                ]]],
            ]),
        ]);

        $field = $this->makeField();

        (new FetchSmapJob($field->id, '2026-08-01', '2026-08-02'))
            ->handle(app(SmapService::class));

        $this->assertDatabaseHas('observations', [
            'field_id' => $field->id,
            'source' => 'SMAP',
            'variable' => 'soil_moisture_am',
            'observed_at' => '2026-08-01',
        ]);
    }

    public function test_capas_leaflet_smap_y_gpm_estan_disponibles(): void
    {
        $layers = app(NasaImageryService::class)->layers();

        $this->assertArrayHasKey('smap_soil_moisture', $layers);
        $this->assertArrayHasKey('imerg_precipitation', $layers);
        $this->assertStringContainsString(
            'SMAP_L3_Active_Passive_Soil_Moisture',
            app(NasaImageryService::class)->tileUrlTemplate('smap_soil_moisture', '2026-08-01')
        );
    }

    public function test_fetch_gpm_job_persiste_granulo_sin_valor_sin_token(): void
    {
        config(['services.earthdata.token' => '']);

        Http::fake([
            'cmr.earthdata.nasa.gov/*' => Http::response([
                'feed' => ['entry' => [[
                    'id' => 'GPM-G1',
                    'title' => 'GPM_3IMERGDF gránulo',
                    'time_start' => '2026-08-01T00:00:00.000Z',
                    'links' => [],
                ]]],
            ]),
        ]);

        $field = $this->makeField();

        (new FetchGpmJob($field->id, '2026-08-01', '2026-08-02'))
            ->handle(app(GpmImergService::class));

        $this->assertDatabaseHas('observations', [
            'field_id' => $field->id,
            'source' => 'GPM',
            'variable' => 'precipitation',
            'observed_at' => '2026-08-01',
        ]);
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
            'irrigation_type' => 'secano',
        ]);
    }
}
