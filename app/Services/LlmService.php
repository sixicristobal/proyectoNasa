<?php

namespace App\Services;

class LlmService
{
    public function __construct(
        protected OllamaService $ollama,
        protected GeminiService $gemini,
    ) {}

    public function recommend(string $topic, string $context): string
    {
        $messages = [[
            'role' => 'user',
            'content' => "Eres un ingeniero agrónomo en Paraguay. Tema: {$topic} (siembra, riego o rotación de cultivos). "
                ."Responde en español, conciso y práctico para pequeños productores.\n\nDatos climáticos recientes:\n{$context}",
        ]];

        return $this->chat($messages);
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  int|null  $timeout  segundos; null usa el valor por defecto del driver
     * @param  array<string, mixed>  $options  opciones de generación del driver
     */
    public function chat(array $messages, ?int $timeout = null, array $options = []): string
    {
        $driver = (string) config('services.llm.driver', 'ollama');

        return $driver === 'gemini'
            ? $this->gemini->chat($messages, $timeout, $options)
            : $this->ollama->chat($messages, $timeout, $options);
    }

    public function driver(): string
    {
        return (string) config('services.llm.driver', 'ollama');
    }
}
