<?php

namespace App\Services\Nasa;

use App\Models\Field;
use Illuminate\Support\Carbon;

class LandsatService
{
    public function __construct(protected CmrService $cmr) {}

    public function fetch(Field $field, Carbon $start, Carbon $end, int $pageSize = 10): array
    {
        // Landsat Collection 2 L2 se consulta mediante HLS (Harmonized Landsat
        // & Sentinel-2), que sí está indexado como gránulos en NASA CMR.
        return $this->cmr->searchGranules('HLSL30', (float) $field->latitude, (float) $field->longitude, $start, $end, $pageSize);
    }
}
