<?php

namespace App\Console\Commands;

use App\Services\NasaPowerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nasa:sync {--lat=-25.2637} {--lon=-57.5759} {--days=7}')]
#[Description('Sincroniza NASA POWER (T2M, T2M_MAX/MIN, PRECTOTCORR, RH2M, WS2M, solar) con upsert')]
class SyncNasaPowerData extends Command
{
    public function handle(NasaPowerService $power): int
    {
        $lat = (float) $this->option('lat');
        $lon = (float) $this->option('lon');
        $days = max(1, (int) $this->option('days'));

        $end = now()->format('Y-m-d');
        $start = now()->subDays($days - 1)->format('Y-m-d');

        $count = $power->syncToDatabase($lat, $lon, $start, $end);
        $this->info("Sincronizadas {$count} filas para ({$lat}, {$lon}) {$start}..{$end}.");

        return self::SUCCESS;
    }
}
