<?php

namespace App\Services;

/**
 * Recomendaciones agronómicas usando SOLO Ollama local (Docker por Tailscale).
 * Sin n8n, sin Gemini, sin DeepSeek.
 */
class LlmService
{
    public function __construct(protected OllamaService $ollama) {}

    public function recommend(string $topic, string $context): string
    {
        $messages = [[
            'role' => 'user',
            'content' => "Eres un ingeniero agrónomo en Paraguay. Tema: {$topic} (siembra, riego o rotación de cultivos). "
                ."Responde en español, conciso y práctico para pequeños productores.\n\nDatos climáticos recientes:\n{$context}",
        ]];

        return $this->ollama->chat($messages);
    }
}
