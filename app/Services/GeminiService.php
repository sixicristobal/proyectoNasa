<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  int|null  $timeout  segundos; por defecto `services.gemini.timeout`
     * @param  array<string, mixed>  $options  opciones de `generationConfig`
     */
    public function chat(array $messages, ?int $timeout = null, array $options = []): string
    {
        $key = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model', 'gemini-3.8-flash');
        $timeout ??= (int) config('services.gemini.timeout', 120);

        if ($key === '') {
            throw new \RuntimeException('Falta GEMINI_API_KEY en .env. Agrega tu clave de Google AI Studio para usar Gemini.');
        }

        $contents = [];
        $system = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $text = (string) ($message['content'] ?? '');

            // Gemini recibe las instrucciones de sistema por un campo separado.
            if ($role === 'system') {
                $system[] = $text;

                continue;
            }

            $contents[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $text]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => array_merge([
                'temperature' => 0.2,
                'maxOutputTokens' => 800,
            ], $options),
        ];

        if ($system !== []) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => implode("\n\n", $system)]],
            ];
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->connectTimeout(10)
                ->timeout($timeout)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $payload);

            $response->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException($this->friendlyError($e, $model), previous: $e);
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                'No se pudo conectar con Gemini (timeout '.$timeout.' s). Verifica tu conexión a internet y la clave GEMINI_API_KEY.',
                previous: $e
            );
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if ($text === '') {
            $reason = (string) data_get($response->json(), 'candidates.0.finishReason', 'sin contenido');

            throw new \RuntimeException('Gemini no devolvió texto ('.$reason.').');
        }

        return $text;
    }

    protected function friendlyError(RequestException $e, string $model): string
    {
        $status = $e->response?->status();
        $body = (string) $e->response?->body();

        return match ($status) {
            400 => 'Gemini rechazó la solicitud (400). Verifica que GEMINI_API_KEY sea válida y que el modelo "'.$model.'" exista. Detalle: '.$body,
            401, 403 => 'Gemini rechazó la autenticación (HTTP '.$status.'). Revisa GEMINI_API_KEY y los permisos del proyecto en Google AI Studio.',
            404 => 'El modelo Gemini "'.$model.'" no existe o no está disponible para esta clave. Configura GEMINI_MODEL con un modelo vigente, por ejemplo gemini-3.8-flash.',
            429 => 'Gemini alcanzó el límite de solicitudes (429). Espera un momento o revisa tu cuota en Google AI Studio.',
            default => 'Gemini devolvió HTTP '.($status ?? 'desconocido').': '.$body,
        };
    }
}
