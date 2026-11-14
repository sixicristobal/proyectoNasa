<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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
            'content' => "You are an agronomist in Paraguay. Topic: {$topic} (planting, irrigation, or crop rotation). "
                .'Give concise, practical advice for small-scale farmers. '
                .__('messages.prompt_language', ['language' => app()->getLocale() === 'en' ? 'English' : 'Spanish'])
                ."\n\nRecent climate data:\n{$context}",
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

        try {
            return $driver === 'gemini'
                ? $this->gemini->chat($messages, $timeout, $options)
                : $this->ollama->chat($messages, $timeout, $options);
        } catch (\Throwable $e) {
            Log::error('LLM chat failed', [
                'driver' => $driver,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function driver(): string
    {
        return (string) config('services.llm.driver', 'ollama');
    }
}
