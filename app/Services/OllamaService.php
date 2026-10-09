<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OllamaService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  int  $timeout  segundos (180 web, 300+ en jobs en 2do plano)
     */
    public function chat(array $messages, int $timeout = 180): string
    {
        $model = (string) config('services.ollama.model', 'llama3.2:3b');
        $host = rtrim((string) config('services.ollama.host', 'http://100.107.189.14:11434'), '/');

        try {
            $response = Http::timeout($timeout)->post(
                $host.'/api/chat',
                [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => false,
                    // keep_alive evita la carga en frío (~2GB) en cada request:
                    // el servidor casero es CPU y cargar tarda + inferencia ~7 tok/s.
                    'keep_alive' => '24h',
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
                'Ollama (Docker por Tailscale) tardó demasiado o no responde en '.config('services.ollama.host').'. '
                .'Verifica en tu servidor casero: `docker ps | grep ollama`, que Tailscale esté conectado (`tailscale status`) y que el modelo esté descargado (`docker exec ollama ollama pull '.config('services.ollama.model').'`).',
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

            return "El modelo Ollama '{$model}' no está en tu Docker. Ejecuta `docker exec <contenedor> ollama pull {$model}` o cambia OLLAMA_MODEL en tu .env. {$hint}";
        }

        if ($e->getCode() === 0 || $status === null) {
            return 'No se pudo conectar con Ollama en '.config('services.ollama.host').'. Verifica Tailscale (`tailscale status`) y que el puerto 11434 esté expuesto en el contenedor.';
        }

        return 'Ollama devolvió HTTP '.$status.': '.$body;
    }
}
