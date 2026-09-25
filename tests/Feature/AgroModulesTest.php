<?php

namespace Tests\Feature;

use App\Models\NasaAgroData;
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

    public function test_recomendaciones_consulta_gemini_con_http_fake(): void
    {
        NasaAgroData::factory()->create(['date' => '2026-09-20', 'ndvi_value' => 0.65]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Riega al amanecer.']]]]],
            ]),
        ]);

        config(['services.gemini.key' => 'fake-key']);

        $this->post('/recomendaciones', ['topic' => 'riego'])
            ->assertRedirect(route('recommendations.index'))
            ->assertSessionHas('recommendation', 'Riega al amanecer.');
    }

    public function test_reforestacion_planifica_con_ollama_fake(): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => Http::response([
                'message' => ['content' => 'Planta Kurupa\'y.'],
            ]),
        ]);

        $this->post('/reforestacion', ['suelo' => 'arenoso'])
            ->assertRedirect(route('reforestation.index'))
            ->assertSessionHas('reforestation', 'Planta Kurupa\'y.');
    }
}
