<?php

namespace App\Jobs;

use App\Jobs\Concerns\PersistsGranules;
use App\Models\Field;
use App\Services\Nasa\LandsatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ObtenerDatosLandsatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PersistsGranules, Queueable, SerializesModels;

    public function handle(LandsatService $servicio): void
    {
        $inicio = Carbon::now()->subDays(30);
        $fin = Carbon::now();

        foreach (Field::all() as $parcela) {
            $granules = $servicio->fetch($parcela, $inicio, $fin);
            $this->persistGranules($parcela, 'LANDSAT', $granules);
        }
    }
}
