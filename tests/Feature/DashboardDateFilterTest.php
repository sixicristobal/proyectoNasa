<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function createRecord(string $date, float $precip): void
    {
        NasaAgroData::factory()->create([
            'lat' => -25.2637,
            'lon' => -57.5759,
            'date' => $date,
            'precip' => $precip,
        ]);
    }

    public function test_dashboard_sin_filtro_muestra_todo_el_historial(): void
    {
        $this->createRecord('2026-09-15', 12.0);
        $this->createRecord('2026-10-05', 3.0);

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('2026-09-15')
            ->assertSee('2026-10-05')
            ->assertSee('(30 días)');
    }

    public function test_dashboard_filtra_por_desde_hasta(): void
    {
        $this->createRecord('2026-09-15', 12.0);
        $this->createRecord('2026-10-05', 3.0);

        $this->get('/?desde=2026-09-01&hasta=2026-09-30')
            ->assertStatus(200)
            ->assertSee('2026-09-15')
            ->assertDontSee('2026-10-05')
            ->assertSee('(09-01 → 09-30)');
    }

    public function test_dashboard_filtra_solo_desde(): void
    {
        $this->createRecord('2026-09-15', 12.0);
        $this->createRecord('2026-10-05', 3.0);

        $this->get('/?desde=2026-10-01')
            ->assertStatus(200)
            ->assertDontSee('2026-09-15')
            ->assertSee('2026-10-05');
    }

    public function test_dashboard_muestra_aviso_si_el_rango_no_tiene_datos(): void
    {
        $this->createRecord('2026-09-15', 12.0);

        $this->get('/?desde=2026-01-01&hasta=2026-01-31')
            ->assertStatus(200)
            ->assertSee('Sin registros entre')
            ->assertDontSee('2026-09-15');
    }

    public function test_dashboard_rechaza_rango_invertido(): void
    {
        $this->get('/?desde=2026-10-10&hasta=2026-10-01')
            ->assertSessionHasErrors('desde');
    }

    public function test_dashboard_rechaza_fecha_invalida(): void
    {
        $this->get('/?desde=no-es-fecha')->assertSessionHasErrors('desde');
    }
}
