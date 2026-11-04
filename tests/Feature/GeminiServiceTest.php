<?php

namespace Tests\Feature;

use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.llm.driver' => 'gemini',
            'services.gemini.key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-3.8-flash',
        ]);
    }

    public function test_llm_service_usa_gemini_y_separa_instrucciones_de_sistema(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Riega al amanecer.']]],
                ]],
            ]),
        ]);

        $reply = app(LlmService::class)->chat([
            ['role' => 'system', 'content' => 'Eres un ingeniero agrónomo.'],
            ['role' => 'user', 'content' => '¿Cuándo riego?'],
        ]);

        $this->assertSame('Riega al amanecer.', $reply);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'models/gemini-3.8-flash:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
                && $request['systemInstruction']['parts'][0]['text'] === 'Eres un ingeniero agrónomo.'
                && $request['contents'][0]['role'] === 'user'
                && $request['contents'][0]['parts'][0]['text'] === '¿Cuándo riego?';
        });
    }

    public function test_chat_usa_gemini_cuando_el_driver_es_gemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Hola productor, riega temprano.']]],
                ]],
            ]),
        ]);

        $this->post('/chat', ['message' => '¿Riego hoy?'])
            ->assertRedirect(route('chat.index'))
            ->assertSessionHas('chat_history', fn ($history) => collect($history)->contains('content', 'Hola productor, riega temprano.'));
    }

    public function test_gemini_sin_api_key_devuelve_error_claro(): void
    {
        config(['services.gemini.key' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GEMINI_API_KEY');

        app(LlmService::class)->chat([['role' => 'user', 'content' => 'Hola']]);
    }

    public function test_gemini_modelo_inexistente_devuelve_error_amigable(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'model not found'], 404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('gemini-3.8-flash');

        app(LlmService::class)->chat([['role' => 'user', 'content' => 'Hola']]);
    }
}
