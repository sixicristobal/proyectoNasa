<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgroModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_recomendaciones_muestra_formulario(): void
    {
        $this->get('/recomendaciones')->assertStatus(200)->assertSee('Siembra');
    }

    public function test_reforestacion_muestra_formulario_y_especies(): void
    {
        $this->get('/reforestacion')->assertStatus(200)->assertSee('Kurupa');
    }

    public function test_recomendaciones_usa_ollama_local_con_http_fake(): void
    {
        NasaAgroData::factory()->create(['date' => '2026-09-20', 'ndvi_value' => 0.65]);

        Http::fake([
            '*11434*' => Http::response([
                'message' => ['content' => 'Riega al amanecer.'],
            ]),
        ]);

        $this->post('/recomendaciones', ['topic' => 'riego'])
            ->assertRedirect(route('recommendations.index'))
            ->assertSessionHas('recommendation', 'Riega al amanecer.');
    }

    public function test_reforestacion_planifica_con_ollama_fake(): void
    {
        Http::fake([
            '*11434*' => Http::response([
                'message' => ['content' => 'Planta Kurupa\'y.'],
            ]),
        ]);

        $this->post('/reforestacion', ['suelo' => 'arenoso'])
            ->assertRedirect(route('reforestation.index'))
            ->assertSessionHas('reforestation', 'Planta Kurupa\'y.');
    }

    public function test_chat_usa_ollama_local_con_http_fake(): void
    {
        NasaAgroData::factory()->create(['date' => '2026-09-20', 'ndvi_value' => 0.65]);

        Http::fake([
            '*11434*' => Http::response([
                'message' => ['content' => 'Hola productor, riega al amanecer.'],
            ]),
        ]);

        $this->post('/chat', ['message' => '¿Riego hoy?'])
            ->assertRedirect(route('chat.index'))
            ->assertSessionHas('chat_history', fn ($h) => collect($h)->contains('content', 'Hola productor, riega al amanecer.'));
    }
}
