<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NasaAgroDataUpsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_inserta_y_actualiza_por_clave_compuesta(): void
    {
        NasaAgroData::upsertRows([[
            'lat' => -25.2637,
            'lon' => -57.5759,
            'date' => '2026-09-20',
            'temp' => 30.5,
            'precip' => 2.0,
            'humidity' => 60.0,
            'ndvi_value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        NasaAgroData::upsertRows([[
            'lat' => -25.2637,
            'lon' => -57.5759,
            'date' => '2026-09-20',
            'temp' => 31.0,
            'precip' => 0.5,
            'humidity' => 55.0,
            'ndvi_value' => 0.72,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        $this->assertSame(1, NasaAgroData::count());
        $row = NasaAgroData::first();
        $this->assertSame(31.0, (float) $row->temp);
        $this->assertSame(0.72, (float) $row->ndvi_value);
    }

    public function test_dashboard_responde_con_chart(): void
    {
        NasaAgroData::factory()->create([
            'lat' => -25.2637,
            'lon' => -57.5759,
            'date' => '2026-09-20',
            'ndvi_value' => 0.65,
        ]);

        $this->get('/')->assertStatus(200)->assertSee('ndviChart');
    }
}
