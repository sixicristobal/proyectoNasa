<?php

namespace App\Jobs;

use App\Jobs\Concerns\PersistsGranules;
use App\Models\Field;
use App\Services\Nasa\ModisExtendedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ObtenerDatosModisExtendidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PersistsGranules, Queueable, SerializesModels;

    public function handle(ModisExtendedService $servicio): void
    {
        $inicio = Carbon::now()->subDays(30);
        $fin = Carbon::now();

        foreach (Field::all() as $parcela) {
            $this->persistGranules($parcela, 'MODIS_LST', $servicio->fetchLst($parcela, $inicio, $fin));
            $this->persistGranules($parcela, 'MODIS_ET', $servicio->fetchEt($parcela, $inicio, $fin));
        }
    }
}
