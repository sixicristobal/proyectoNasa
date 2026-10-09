<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManualRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeNasaPower(): void
    {
        Http::fake([
            'power.larc.nasa.gov/*' => Http::response([
                'properties' => ['parameter' => [
                    'T2M' => ['20261008' => 25.0, '20261007' => 24.0],
                    'T2M_MAX' => ['20261008' => 30.0, '20261007' => 29.0],
                    'T2M_MIN' => ['20261008' => 18.0, '20261007' => 17.0],
                    'PRECTOTCORR' => ['20261008' => 2.5, '20261007' => 0.0],
                    'RH2M' => ['20261008' => 70.0, '20261007' => 65.0],
                    'WS2M' => ['20261008' => 3.0, '20261007' => 2.0],
                    'ALLSKY_SFC_SW_DWN' => ['20261008' => 20.0, '20261007' => 21.0],
                ]],
            ]),
        ]);
    }

    public function test_boton_actualizar_sincroniza_y_redirige(): void
    {
        $this->fakeNasaPower();

        $response = $this->post('/actualizar', ['lat' => -25.2637, 'lon' => -57.5759]);

        $response->assertRedirect(route('dashboard', ['lat' => -25.2637, 'lon' => -57.5759]));
        $response->assertSessionHas('refresh_status');
        $this->assertSame(2, NasaAgroData::count());
        $this->assertSame(30.0, (float) NasaAgroData::where('date', '2026-10-08')->first()->temp_max);
    }

    public function test_actualizar_reusa_datos_existentes_sin_duplicar(): void
    {
        $this->fakeNasaPower();

        $this->post('/actualizar')->assertSessionHas('refresh_status');
        $this->post('/actualizar')->assertSessionHas('refresh_status');

        $this->assertSame(2, NasaAgroData::count());
    }

    public function test_actualizar_rechaza_coordenadas_invalidas(): void
    {
        $this->post('/actualizar', ['lat' => 999])->assertSessionHasErrors('lat');
        $this->assertSame(0, NasaAgroData::count());
    }

    public function test_dashboard_muestra_boton_actualizar(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('Actualizar datos');
    }
}
