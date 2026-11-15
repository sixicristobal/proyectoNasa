<?php

namespace Tests\Feature;

use App\Jobs\ObtenerDatosSrtmJob;
use App\Models\Farm;
use App\Models\Observation;
use App\Models\User;
use App\Services\Nasa\CmrService;
use App\Services\Nasa\SrtmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdvancedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_cmr_service_puede_omitir_el_filtro_temporal(): void
    {
        Http::fake([
            'cmr.earthdata.nasa.gov/*' => Http::response(['feed' => ['entry' => []]]),
        ]);

        app(CmrService::class)->searchGranules(
            'SRTMGL1',
            -25.17,
            -57.48,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-09-23'),
            5,
            [],
            false
        );

        Http::assertSent(fn ($request): bool => ! isset($request['temporal']));
    }

    public function test_srtm_service_no_aplica_filtro_temporal(): void
    {
        Http::fake([
            'cmr.earthdata.nasa.gov/*' => Http::response(['feed' => ['entry' => []]]),
        ]);

        app(SrtmService::class)->fetch($this->makeField(), Carbon::now()->subDays(30), Carbon::now());

        Http::assertSent(fn ($request): bool => $request['short_name'] === 'SRTMGL1'
            && ! isset($request['temporal']));
    }

    public function test_pagina_de_datos_avanzados_muestra_enlace_de_descarga(): void
    {
        Http::fake([
            'cmr.earthdata.nasa.gov/*' => Http::response([
                'feed' => ['entry' => [[
                    'id' => 'HLS.L30.T21JVN.2026289T134809.v2.0',
                    'time_start' => '2026-10-16T00:00:00.000Z',
                    'time_end' => '2026-10-16T23:59:59.000Z',
                    'links' => [[
                        'href' => 'https://example.com/HLS.L30.tif',
                        'rel' => 'http://esipfed.org/ns/fedsearch/1.1/data#',
                    ]],
                ]]],
            ]),
        ]);

        $this->get('/datos-avanzados?servicio=landsat&lat=-25.2637&lon=-57.5759')
            ->assertOk()
            ->assertSee('HLS.L30.T21JVN.2026289T134809.v2.0')
            ->assertSee('/datos-avanzados/descargar?', false)
            ->assertDontSee('https://example.com/HLS.L30.tif', false);
    }

    public function test_job_srtm_persiste_los_granulos_en_observations(): void
    {
        Http::fake([
            'cmr.earthdata.nasa.gov/*' => Http::response([
                'feed' => ['entry' => [[
                    'id' => 'S26W058.SRTMGL1',
                    'time_start' => '2000-02-11T00:00:00.000Z',
                    'links' => [],
                ]]],
            ]),
        ]);

        $field = $this->makeField();

        (new ObtenerDatosSrtmJob)->handle(app(SrtmService::class));

        $observation = Observation::query()->where('source', 'SRTM')->first();

        $this->assertNotNull($observation);
        $this->assertSame($field->id, $observation->field_id);
        $this->assertSame('2000-02-11', $observation->observed_at->format('Y-m-d'));
        $this->assertSame('S26W058.SRTMGL1', $observation->raw_response['id']);
    }

    public function test_descarga_rechaza_url_sin_firma(): void
    {
        $url = 'https://data.lpdaac.earthdatacloud.nasa.gov/lp-prod-protected/demo.tif';

        $this->get('/datos-avanzados/descargar?url='.urlencode($url))->assertForbidden();
    }

    public function test_descarga_rechaza_host_no_permitido(): void
    {
        $url = 'https://evil.example.com/archivo.tif';
        $signed = URL::temporarySignedRoute('advanced.download', now()->addMinutes(5), ['url' => $url]);

        $this->get($signed)->assertStatus(400);
    }

    public function test_descarga_autenticada_devuelve_el_archivo(): void
    {
        config(['services.earthdata.token' => 'token-de-prueba']);

        Http::fake([
            'data.lpdaac.earthdatacloud.nasa.gov/*' => Http::response('contenido-hdf', 200),
        ]);

        $url = 'https://data.lpdaac.earthdatacloud.nasa.gov/lp-prod-protected/demo.h5';
        $signed = URL::temporarySignedRoute('advanced.download', now()->addMinutes(5), ['url' => $url]);

        $this->get($signed)
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=demo.h5');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer token-de-prueba'));
    }

    public function test_descarga_sin_autorizacion_redirige_con_error(): void
    {
        config(['services.earthdata.token' => 'token-de-prueba']);

        Http::fake([
            'data.lpdaac.earthdatacloud.nasa.gov/*' => Http::response('', 403),
        ]);

        $url = 'https://data.lpdaac.earthdatacloud.nasa.gov/lp-prod-protected/demo.h5';
        $signed = URL::temporarySignedRoute('advanced.download', now()->addMinutes(5), ['url' => $url]);

        $this->get($signed)
            ->assertRedirect(route('advanced.index'))
            ->assertSessionHas('download_error');
    }

    private function makeField(): \App\Models\Field    {
        $user = User::factory()->create();

        $farm = Farm::create([
            'user_id' => $user->id,
            'name' => 'Finca Test',
            'region' => 'Itapúa',
            'country' => 'Paraguay',
        ]);

        return \App\Models\Field::create([
            'farm_id' => $farm->id,
            'name' => 'Parcela 1',
            'latitude' => -25.2637,
            'longitude' => -57.5759,
            'area_ha' => 5.5,
            'irrigation_type' => 'secano',
        ]);
    }
}
