<?php

namespace App\Jobs;

use App\Models\Field;
use App\Services\Nasa\ModisExtendedService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ObtenerDatosModisExtendidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ModisExtendedService $servicio): void
    {
        $inicio = Carbon::now()->subDays(30);
        $fin = Carbon::now();

        foreach (Field::all() as $parcela) {
            $servicio->fetchLst($parcela, $inicio, $fin);
            $servicio->fetchEt($parcela, $inicio, $fin);
        }
    }
}
