<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageryDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_imagenes_muestra_visor_gibs(): void
    {
        $this->get('/imagenes')
            ->assertStatus(200)
            ->assertSee('gibs.earthdata.nasa.gov')
            ->assertSee('Color verdadero')
            ->assertSee('Falso color');
    }

    public function test_imagenes_fija_fecha_futura_a_valor_seguro(): void
    {
        $safe = now()->subDays(4)->format('Y-m-d');

        $this->get('/imagenes?fecha=2030-01-01')
            ->assertStatus(200)
            ->assertSee($safe);
    }

    public function test_imagenes_capa_invalida_usa_por_defecto(): void
    {
        $this->get('/imagenes?capa=capa_inexistente')
            ->assertStatus(200)
            ->assertSee('MODIS_Terra_CorrectedReflectance_TrueColor');
    }

    public function test_dashboard_muestra_lluvia_del_mes(): void
    {
        NasaAgroData::factory()->create([
            'lat' => -25.2637,
            'lon' => -57.5759,
            'date' => '2026-09-29',
            'precip' => 36.3,
        ]);

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('precipChart')
            ->assertSee('Lluvia acumulada')
            ->assertSee('Lluvias y tormentas del mes');
    }
}
