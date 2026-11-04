<?php

namespace App\Jobs;

use App\Models\Field;
use App\Services\Nasa\SrtmService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ObtenerDatosSrtmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SrtmService $servicio): void
    {
        $inicio = Carbon::now()->subDays(30);
        $fin = Carbon::now();

        foreach (Field::all() as $parcela) {
            $servicio->fetch($parcela, $inicio, $fin);
        }
    }
}
