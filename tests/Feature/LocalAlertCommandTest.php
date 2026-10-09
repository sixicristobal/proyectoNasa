<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalAlertCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_alerts_genera_alerta_sequia_y_notifica(): void
    {
        $user = User::factory()->create();

        // 7 días secos → debe disparar riesgo sequía (temp_min alto para no disparar helada)
        foreach (range(0, 6) as $i) {
            NasaAgroData::factory()->create([
                'date' => now()->subDays($i)->format('Y-m-d'),
                'temp' => 28.0,
                'temp_min' => 18.0,
                'precip' => 0.0,
                'humidity' => 60.0,
                'wind_speed' => 2.0,
            ]);
        }

        Http::fake([
            '*11434*' => Http::response([
                'message' => ['content' => 'Riesgo de sequía: riega al amanecer.'],
            ]),
        ]);

        $this->artisan('agro:check-alerts')->assertSuccessful();

        $this->assertDatabaseHas('alerts', ['risk_type' => 'sequia', 'source' => 'laravel-ollama']);
        $this->assertCount(1, $user->refresh()->notifications);
    }

    public function test_check_alerts_sin_riesgo_no_crea_nada(): void
    {
        foreach (range(0, 6) as $i) {
            NasaAgroData::factory()->create([
                'date' => now()->subDays($i)->format('Y-m-d'),
                'temp' => 25.0,
                'temp_min' => 15.0,
                'precip' => 5.0,
                'humidity' => 70.0,
                'wind_speed' => 2.0,
            ]);
        }

        $this->artisan('agro:check-alerts')->assertSuccessful();
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_check_alerts_detecta_lluvia_fuerte(): void
    {
        foreach (range(0, 6) as $i) {
            NasaAgroData::factory()->create([
                'date' => now()->subDays($i)->format('Y-m-d'),
                'temp' => 24.0,
                'temp_min' => 16.0,
                'precip' => $i === 0 ? 36.0 : 2.0,
                'humidity' => 75.0,
                'wind_speed' => 2.0,
            ]);
        }

        Http::fake([
            '*11434*' => Http::response([
                'message' => ['content' => 'Lluvia fuerte: revisa drenaje.'],
            ]),
        ]);

        $this->artisan('agro:check-alerts')->assertSuccessful();

        $this->assertDatabaseHas('alerts', ['risk_type' => 'lluvia_fuerte']);
    }

    public function test_check_alerts_detecta_tormenta_probable(): void
    {
        foreach (range(0, 6) as $i) {
            NasaAgroData::factory()->create([
                'date' => now()->subDays($i)->format('Y-m-d'),
                'temp' => 24.0,
                'temp_min' => 16.0,
                'precip' => $i === 0 ? 15.0 : 2.0,
                'humidity' => 85.0,
                'wind_speed' => $i === 0 ? 12.0 : 2.0, // 12 m/s ≈ 43 km/h
            ]);
        }

        Http::fake([
            '*11434*' => Http::response([
                'message' => ['content' => 'Tormenta probable: asegura todo.'],
            ]),
        ]);

        $this->artisan('agro:check-alerts')->assertSuccessful();

        $this->assertDatabaseHas('alerts', ['risk_type' => 'tormenta_probable']);
    }

    public function test_check_alerts_no_duplica_al_repetir(): void
    {
        NasaAgroData::factory()->create([
            'date' => now()->format('Y-m-d'),
            'temp' => 24.0,
            'temp_min' => 16.0,
            'precip' => 36.0,
            'humidity' => 75.0,
            'wind_speed' => 2.0,
        ]);

        Http::fake([
            '*11434*' => Http::response([
                'message' => ['content' => 'Lluvia fuerte.'],
            ]),
        ]);

        $this->artisan('agro:check-alerts')->assertSuccessful();
        $this->artisan('agro:check-alerts')->assertSuccessful();

        $this->assertSame(1, \App\Models\Alert::where('risk_type', 'lluvia_fuerte')->count());
    }

    public function test_rutas_n8n_responden_gone(): void
    {
        $this->postJson('/api/n8n/alerts', ['risk_type' => 'x'])->assertStatus(410);
        $this->postJson('/api/n8n/sync-nasa')->assertStatus(410);
    }
}
