<?php

namespace App\Console\Commands;

use App\Services\NasaPowerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('nasa:sync {--lat=-25.2637} {--lon=-57.5759} {--days=7} {--start=} {--end=}')]
#[Description('Sincroniza NASA POWER (T2M, T2M_MAX/MIN, PRECTOTCORR, RH2M, WS2M, solar) con upsert')]
class SyncNasaPowerData extends Command
{
    /**
     * Ventana máxima por llamada a la API de NASA POWER.
     */
    public const int MAX_RANGE_DAYS = 366;

    public function handle(NasaPowerService $power): int
    {
        $lat = (float) $this->option('lat');
        $lon = (float) $this->option('lon');
        $days = max(1, (int) $this->option('days'));

        $range = $this->resolveRange($days);
        if ($range === null) {
            return self::FAILURE;
        }

        [$start, $end] = $range;

        $count = $power->syncToDatabase($lat, $lon, $start, $end);
        $this->info("Sincronizadas {$count} filas para ({$lat}, {$lon}) {$start}..{$end}.");

        return self::SUCCESS;
    }

    /**
     * --start/--end mandan sobre --days; si faltan, se calcula la ventana hacia atrás desde --end.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function resolveRange(int $days): ?array
    {
        $startInput = trim((string) $this->option('start'));
        $endInput = trim((string) $this->option('end'));

        try {
            $end = $endInput !== '' ? Carbon::parse($endInput)->startOfDay() : now()->startOfDay();
            $start = $startInput !== '' ? Carbon::parse($startInput)->startOfDay() : $end->copy()->subDays($days - 1);
        } catch (\Throwable) {
            $this->error('Fechas inválidas. Usa --start=YYYY-MM-DD y --end=YYYY-MM-DD.');

            return null;
        }

        if ($start->greaterThan($end)) {
            $this->error('La fecha --start no puede ser posterior a --end.');

            return null;
        }

        $rangeDays = (int) $start->diffInDays($end) + 1;
        if ($rangeDays > self::MAX_RANGE_DAYS) {
            $this->error('El rango no puede superar '.self::MAX_RANGE_DAYS." días (recibido {$rangeDays}).");

            return null;
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
}
