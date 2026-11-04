<?php

namespace App\Services\Nasa;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AppEarsService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config(
            'services.appeears.base_url',
            'https://appeears.earthdatacloud.nasa.gov/api'
        ), '/');
    }

    public function enabled(): bool
    {
        return trim((string) config('services.earthdata.token')) !== '';
    }

    /**
     * Crea una tarea point sample y devuelve el task_id.
     *
     * @param  array<int, array{product: string, layer: string}>  $layers
     * @param  array<int, array{id: string, latitude: float, longitude: float}>  $coordinates
     */
    public function createPointTask(
        string $taskName,
        array $layers,
        array $coordinates,
        Carbon $start,
        Carbon $end
    ): string {
        $this->ensureToken();

        $payload = [
            'task_type' => 'point',
            'task_name' => $this->safeTaskName($taskName),
            'params' => [
                'dates' => [[
                    'startDate' => $start->format('m-d-Y'),
                    'endDate' => $end->format('m-d-Y'),
                ]],
                'layers' => $layers,
                'coordinates' => $coordinates,
                'output' => [
                    'format' => ['type' => 'geotiff'],
                    'projection' => 'geographic',
                ],
            ],
        ];

        Log::info('[NASA AppEEARS] creando tarea point', [
            'task_name' => $payload['task_name'],
            'layers' => $layers,
            'coordinates' => $coordinates,
        ]);

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(60)
            ->post($this->baseUrl.'/task', $payload);

        $response->throw();

        $taskId = (string) $response->json('task_id');

        if ($taskId === '') {
            throw new RuntimeException('AppEEARS no devolvió task_id.');
        }

        return $taskId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTask(string $taskId): array
    {
        $this->ensureToken();

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(60)
            ->get($this->baseUrl.'/task/'.$taskId);

        $response->throw();

        return (array) $response->json();
    }

    /**
     * Espera hasta que la tarea AppEEARS termine (done) o falle.
     *
     * @return array<string, mixed>
     */
    public function waitForTask(string $taskId, int $maxWaitSeconds = 1800, int $pollSeconds = 20): array
    {
        $startedAt = time();

        while (true) {
            $task = $this->getTask($taskId);
            $status = strtolower((string) ($task['status'] ?? ''));

            if ($status === 'done') {
                return $task;
            }

            if (in_array($status, ['failed', 'error', 'cancelled'], true)) {
                throw new RuntimeException(
                    "La tarea AppEEARS {$taskId} falló con estado '{$status}': "
                    .json_encode($task['errors'] ?? $task)
                );
            }

            if ((time() - $startedAt) >= $maxWaitSeconds) {
                throw new RuntimeException(
                    "Timeout esperando AppEEARS task {$taskId} (estado actual: {$status})."
                );
            }

            sleep($pollSeconds);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBundle(string $taskId): array
    {
        $this->ensureToken();

        $response = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(60)
            ->get($this->baseUrl.'/bundle/'.$taskId);

        $response->throw();

        return (array) $response->json('files', []);
    }

    /**
     * Descarga un archivo del bundle y devuelve su ruta local.
     */
    public function downloadFile(string $taskId, string $fileId, ?string $fileName = null): string
    {
        $this->ensureToken();

        $fileName ??= $fileId;
        $path = 'nasa/appeears/'.$taskId.'/'.basename($fileName);

        $response = Http::withToken($this->token())
            ->timeout(300)
            ->get($this->baseUrl.'/bundle/'.$taskId.'/'.$fileId);

        $response->throw();

        Storage::disk('local')->put($path, $response->body());

        return Storage::disk('local')->path($path);
    }

    /**
     * Lee el CSV point sample de AppEEARS y devuelve una fila por fecha.
     *
     * @return array<int, array{date: Carbon, value: float|null, row: array<string, string>}>
     */
    public function parsePointCsv(string $path, string $layer): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el CSV de AppEEARS: {$path}");
        }

        try {
            $header = $this->readHeader($handle);
            $rows = [];
            $dateIndex = $this->findColumn($header, ['date']);
            $valueIndex = $this->findColumn($header, [$layer, 'value']);

            if ($dateIndex === null) {
                throw new RuntimeException('El CSV de AppEEARS no contiene una columna Date.');
            }

            if ($valueIndex === null) {
                throw new RuntimeException("El CSV de AppEEARS no contiene la capa '{$layer}'.");
            }

            while (($data = fgetcsv($handle)) !== false) {
                if ($data === [null] || $data === false) {
                    continue;
                }

                $rawDate = trim((string) ($data[$dateIndex] ?? ''));
                if ($rawDate === '') {
                    continue;
                }

                $assoc = [];
                foreach ($header as $i => $name) {
                    $assoc[$name] = trim((string) ($data[$i] ?? ''));
                }

                $rows[] = [
                    'date' => $this->parseDate($rawDate),
                    'value' => $this->parseValue($data[$valueIndex] ?? null),
                    'row' => $assoc,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array<int, string>
     */
    protected function readHeader($handle): array
    {
        while (($line = fgetcsv($handle)) !== false) {
            $line = array_map(fn ($value) => trim((string) $value), $line);
            $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $line[0] ?? '') ?? '';

            $lower = array_map('strtolower', $line);

            if (in_array('date', $lower, true)
                || collect($lower)->contains(fn ($value) => str_contains($value, 'date'))) {
                return $line;
            }
        }

        throw new RuntimeException('No se encontró el encabezado del CSV de AppEEARS.');
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $candidates
     */
    protected function findColumn(array $header, array $candidates): ?int
    {
        foreach ($header as $index => $name) {
            $normalized = strtolower($name);

            foreach ($candidates as $candidate) {
                if ($normalized === strtolower($candidate)
                    || str_contains($normalized, strtolower($candidate))) {
                    return $index;
                }
            }
        }

        return null;
    }

    protected function parseDate(string $value): Carbon
    {
        foreach (['Y-m-d', 'm-d-Y', 'Y/m/d', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
                // Probar el siguiente formato.
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            throw new RuntimeException("Fecha inválida en CSV de AppEEARS: {$value}", previous: $e);
        }
    }

    protected function parseValue(mixed $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '' || in_array(strtolower($value), ['nan', 'null', 'na', 'n/a'], true)) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function safeTaskName(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name) ?? 'nasa_task';

        return substr($safe, 0, 80);
    }

    protected function ensureToken(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Falta NASA_EARTHDATA_TOKEN en .env para usar AppEEARS.');
        }
    }

    protected function token(): string
    {
        return (string) config('services.earthdata.token');
    }
}
