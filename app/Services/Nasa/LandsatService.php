<?php

namespace App\Services\Nasa;

use App\Models\Field;
use Illuminate\Support\Carbon;

class LandsatService
{
    public function __construct(protected CmrService $cmr) {}

    public function fetch(Field $field, Carbon $start, Carbon $end, int $pageSize = 10): array
    {
        return $this->cmr->searchGranules('LANDSAT_OT_L2', (float) $field->latitude, (float) $field->longitude, $start, $end, $pageSize);
    }
}
" filePath="/var/www/html/ProyectoNasa/ProyectoNASA/app/Services/Nasa/LandsatService.php