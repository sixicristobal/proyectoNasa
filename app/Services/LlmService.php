<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmService
{
    public function recommend(string $topic, string $context): string
    {
        $prompt = "Eres un ingeniero agrónomo en Paraguay. Tema: {$topic} (siembra, riego o rotación de cultivos). "
            ."Responde en español, conciso y práctico para pequeños productores.\n\nDatos climáticos recientes:\n{$context}";

        return config('services.llm.driver', 'gemini') === 'deepseek'
            ? $this->viaDeepSeek($prompt)
            : $this->viaGemini($prompt);
    }

    protected function viaGemini(string $prompt): string
    {
        $key = (string) config('services.gemini.key');
        abort_if($key === '', 503, 'Falta GEMINI_API_KEY en tu .env.');

        $response = Http::timeout(120)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
            [
                'query' => ['key' => $key],
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]
        );

        $response->throw();

        return (string) ($response->json('candidates.0.content.parts.0.text') ?? '');
    }

    protected function viaDeepSeek(string $prompt): string
    {
        $key = (string) config('services.deepseek.key');
        abort_if($key === '', 503, 'Falta DEEPSEEK_API_KEY en tu .env.');

        $response = Http::timeout(120)->withToken($key)->post(
            'https://api.deepseek.com/chat/completions',
            [
                'model' => 'deepseek-chat',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.3,
            ]
        );

        $response->throw();

        return (string) ($response->json('choices.0.message.content') ?? '');
    }
}
