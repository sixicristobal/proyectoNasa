<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Ingesta NASA POWER (reemplaza el Cron de n8n) ─────────────────────
// Consume la API de la NASA con Http (NasaPowerService) y guarda el JSON
// crudo ya normalizado en MariaDB (tabla nasa_agro_data) con upsert.
// Requiere el cron del sistema: * * * * * php artisan schedule:run
Schedule::command('nasa:sync --lat=-25.2637 --lon=-57.5759 --days=10')
    ->dailyAt('03:00')
    ->timezone('America/Asuncion')
    ->withoutOverlapping()
    ->onSuccess(fn () => info('[scheduler] nasa:sync OK'))
    ->onFailure(fn () => report('[scheduler] nasa:sync FALLÓ'));

// Revisa los últimos datos y genera Alert + notificación (reemplaza
// el flujo n8n: Code -> Gemini -> POST /api/n8n/alerts).
Schedule::command('agro:check-alerts')
    ->dailyAt('03:30')
    ->timezone('America/Asuncion')
    ->withoutOverlapping();
