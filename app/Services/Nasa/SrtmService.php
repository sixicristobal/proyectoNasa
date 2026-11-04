<?php

namespace App\Services\Nasa;

use App\Models\Field;
use Illuminate\Support\Carbon;

class SrtmService
{
    public function __construct(protected CmrService $cmr) {}

    public function fetch(Field $field, Carbon $start, Carbon $end, string $shortName = 'SRTMGL1', int $pageSize = 5): array
    {
        return $this->cmr->searchGranules($shortName, (float) $field->latitude, (float) $field->longitude, $start, $end, $pageSize);
    }
}
" filePath="/var/www/html/ProyectoNasa/ProyectoNASA/app/Services/Nasa/SrtmService.php