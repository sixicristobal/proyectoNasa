<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\NasaAgroData;
use App\Models\User;
use App\Notifications\RiskAlertNotification;
use App\Services\OllamaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agro:check-alerts {--lat=} {--lon=} {--days=10}')]
#[Description('Revisa últimos datos NASA y genera alertas locales (sequía/helada/lluvia/tormenta) con dictamen Ollama')]
class CheckAgroAlerts extends Command
{
    public function handle(OllamaService $ollama): int
    {
        $lat = $this->option('lat') !== null ? (float) $this->option('lat') : null;
        $lon = $this->option('lon') !== null ? (float) $this->option('lon') : null;
        $days = max(3, (int) $this->option('days'));

        $query = NasaAgroData::query()->orderByDesc('date')->limit($days);

        if ($lat !== null && $lon !== null) {
            $query->near($lat, $lon);
        }

        // Solo días con dato real (NASA POWER trae NULL en los 3-5 días más recientes por lag NRT).
        $rows = $query->get()->filter(fn ($r) => $r->precip !== null || $r->temp !== null)->values();

        if ($rows->isEmpty()) {
            $this->warn('Sin datos en nasa_agro_data. Ejecuta nasa:sync primero.');

            return self::SUCCESS;
        }

        $risks = $this->detectRisks($rows);

        if ($risks === []) {
            $this->info('Sin riesgos detectados.');

            return self::SUCCESS;
        }

        $context = $rows->take(7)->map(fn ($r) => sprintf(
            'Fecha %s: temp %.1F C (máx %.1F/mín %.1F), lluvia %.1F mm, humedad %.0F %%, viento %.1F m/s, NDVI %s.',
            $r->date->format('Y-m-d'),
            (float) $r->temp,
            (float) $r->temp_max,
            (float) $r->temp_min,
            (float) $r->precip,
            (float) $r->humidity,
            (float) $r->wind_speed,
            $r->ndvi_value !== null ? number_format((float) $r->ndvi_value, 3) : 'sin dato'
        ))->implode("\n");

        foreach ($risks as $risk) {
            // Antidup: no repetir la misma alerta si ya existe para esa fecha.
            $exists = Alert::query()
                ->where('risk_type', $risk['risk_type'])
                ->whereDate('data_date', $risk['data_date'])
                ->exists();

            if ($exists) {
                $this->line("Alerta {$risk['risk_type']} del {$risk['data_date']} ya existe, se omite.");

                continue;
            }

            // ── Inferencia: dictamen con Ollama local (Docker por Tailscale) ──
            // Timeout largo: corre en scheduler/2do plano y el servidor es CPU (~7 tok/s).
            try {
                $message = $ollama->chat([[
                    'role' => 'user',
                    'content' => "Eres un experto agroclimático en Paraguay. Riesgo detectado: {$risk['risk_type']}. {$risk['detail']}\n\n"
                        ."Datos recientes:\n{$context}\n\nRedacta en español, en 3-4 líneas, qué debe hacer un pequeño productor esta semana.",
                ]], timeout: 300);
            } catch (\Throwable $e) {
                // Si Ollama está apagado o lento, guarda la alerta igual con el detalle
                // técnico corto (el diagnóstico completo queda en el log, no en pantalla).
                \Illuminate\Support\Facades\Log::warning('Ollama no disponible en agro:check-alerts: '.$e->getMessage());
                $message = $risk['detail'].' (Dictamen IA pendiente: Ollama no respondió a tiempo, se reintentará en la próxima revisión.)';
            }

            $alert = Alert::create([
                'risk_type' => $risk['risk_type'],
                'message' => $message,
                'data_date' => $risk['data_date'],
                'source' => 'laravel-ollama',
            ]);

            $user = User::query()->first();
            if ($user) {
                $user->notify(new RiskAlertNotification($alert));
            }

            $this->info("Alerta {$risk['risk_type']} creada (id {$alert->id}).");
        }

        return self::SUCCESS;
    }

    /**
     * Reglas de detección sobre los últimos días con dato.
     *
     * @param  \Illuminate\Support\Collection<int, NasaAgroData>  $rows
     * @return array<int, array{risk_type: string, detail: string, data_date: string}>
     */
    public function detectRisks($rows): array
    {
        $risks = [];
        $latest = $rows->first()->date->format('Y-m-d');

        $avgPrecip = (float) $rows->avg('precip');
        $tempMinRows = $rows->whereNotNull('temp_min');
        $minTempMin = $tempMinRows->isNotEmpty() ? (float) $tempMinRows->min('temp_min') : null;
        $avgHumidity = (float) $rows->avg('humidity');

        // ── Lluvias y tormentas (día por día) ──
        foreach ($rows as $row) {
            $date = $row->date->format('Y-m-d');
            $precip = (float) $row->precip;
            $windKmh = (float) $row->wind_speed * 3.6;
            $humidity = (float) $row->humidity;

            if ($precip >= 50) {
                $risks[] = [
                    'risk_type' => 'lluvia_extrema',
                    'detail' => sprintf('Lluvia extrema el %s: %.1F mm en el día (umbral 50). Riesgo de inundación y pérdida de cultivos.', $date, $precip),
                    'data_date' => $date,
                ];
            } elseif ($precip >= 20) {
                $risks[] = [
                    'risk_type' => 'lluvia_fuerte',
                    'detail' => sprintf('Lluvia fuerte el %s: %.1F mm en el día (umbral 20). Revisa drenaje y encharcamiento.', $date, $precip),
                    'data_date' => $date,
                ];
            }

            if ($precip >= 10 && $windKmh >= 40 && $humidity >= 80) {
                $risks[] = [
                    'risk_type' => 'tormenta_probable',
                    'detail' => sprintf('Tormenta probable el %s: %.1F mm + viento %.0F km/h + humedad %.0F %%. Asegura estructuras y animales.', $date, $precip, $windKmh, $humidity),
                    'data_date' => $date,
                ];
            }
        }

        // ── Acumulado 3 días (encharcamiento) ──
        $last3 = $rows->take(3);
        if ($last3->count() === 3) {
            $accum = (float) $last3->sum('precip');
            if ($accum >= 60) {
                $risks[] = [
                    'risk_type' => 'inundacion_encharcamiento',
                    'detail' => sprintf('Acumulado 3 días: %.1F mm (umbral 60). Riesgo de encharcamiento y hongos.', $accum),
                    'data_date' => $latest,
                ];
            }
        }

        // ── Sequía / helada / estrés (ventana completa) ──
        if ($avgPrecip < 1.0) {
            $risks[] = [
                'risk_type' => 'sequia',
                'detail' => sprintf('Precipitación media %d días: %.1F mm/día (umbral <1.0).', $rows->count(), $avgPrecip),
                'data_date' => $latest,
            ];
        }

        if ($minTempMin !== null && $minTempMin < 3.0) {
            $risks[] = [
                'risk_type' => 'helada',
                'detail' => sprintf('Temperatura mínima %d días: %.1F °C (umbral <3). Protege almácigos y cultivos sensibles.', $rows->count(), $minTempMin),
                'data_date' => $latest,
            ];
        } elseif ($minTempMin === null && (float) $rows->min('temp') < 5.0) {
            // Fallback para filas viejas sin temp_min.
            $risks[] = [
                'risk_type' => 'helada',
                'detail' => sprintf('Temperatura media mínima %d días: %.1F °C (umbral <5).', $rows->count(), (float) $rows->min('temp')),
                'data_date' => $latest,
            ];
        }

        if ($avgHumidity < 40) {
            $risks[] = [
                'risk_type' => 'estres_hidrico',
                'detail' => sprintf('Humedad media: %.0F %% (umbral <40).', $avgHumidity),
                'data_date' => $latest,
            ];
        }

        return $risks;
    }
}
