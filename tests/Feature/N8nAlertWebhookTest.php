<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class N8nAlertWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_guarda_alerta_y_notifica(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/n8n/alerts', [
            'risk_type' => 'sequia',
            'message' => 'Riesgo alto de sequía en los próximos 7 días.',
            'data_date' => '2026-09-25',
        ]);

        $response->assertCreated()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('alerts', ['risk_type' => 'sequia']);
        $this->assertCount(1, $user->notifications);
    }

    public function test_webhook_rechaza_payload_invalido(): void
    {
        $this->postJson('/api/n8n/alerts', ['risk_type' => 'x'])
            ->assertUnprocessable();
    }
}
