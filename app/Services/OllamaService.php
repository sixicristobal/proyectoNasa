<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OllamaService
{
    public function chat(array $messages): string
    {
        $model = (string) config('services.ollama.model', 'llama3.2');

        try {
            $response = Http::timeout(180)->post(
                rtrim((string) config('services.ollama.host'), '/').'/api/chat',
                [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.2,
                        'num_ctx' => 4096,
                        'num_predict' => 400,
                    ],
                ]
            );

            $response->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException($this->friendlyError($e, $model), previous: $e);
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                'Ollama tardó demasiado o no responde en '.config('services.ollama.host').'. '
                .'Verifica que `ollama serve` esté corriendo y que haya RAM libre (el modelo de 4 GB carga lento si el equipo está exigido).',
                previous: $e
            );
        }

        return (string) ($response->json('message.content') ?? '');
    }

    /**
     * @return list<string>
     */
    public function availableModels(): array
    {
        try {
            $response = Http::timeout(10)->get(
                rtrim((string) config('services.ollama.host'), '/').'/api/tags'
            );
            $response->throw();
        } catch (\Throwable) {
            return [];
        }

        return collect($response->json('models', []))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    protected function friendlyError(RequestException $e, string $model): string
    {
        $status = $e->response?->status();
        $body = (string) $e->response?->body();

        if ($status === 404 && str_contains($body, 'not found')) {
            $installed = $this->availableModels();
            $hint = $installed === []
                ? 'No se pudo listar modelos instalados.'
                : 'Instalados: '.implode(', ', $installed).'.';

            return "El modelo Ollama '{$model}' no está instalado en tu PC. Ejecuta `ollama pull {$model}` o cambia OLLAMA_MODEL en tu .env. {$hint}";
        }

        if ($e->getCode() === 0 || $status === null) {
            return 'No se pudo conectar con Ollama en '.config('services.ollama.host').'. Verifica que `ollama serve` esté corriendo.';
        }

        return 'Ollama devolvió HTTP '.$status.': '.$body;
    }
}
