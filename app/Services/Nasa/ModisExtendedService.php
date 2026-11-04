<?php

namespace App\Services\Nasa;

use App\Models\Field;
use Illuminate\Support\Carbon;

class ModisExtendedService
{
    public function __construct(protected CmrService $cmr) {}

    public function fetchLst(Field $field, Carbon $start, Carbon $end, int $pageSize = 10): array
    {
        return $this->cmr->searchGranules('MOD11A2', (float) $field->latitude, (float) $field->longitude, $start, $end, $pageSize);
    }

    public function fetchEt(Field $field, Carbon $start, Carbon $end, int $pageSize = 10): array
    {
        return $this->cmr->searchGranules('MOD16A2', (float) $field->latitude, (float) $field->longitude, $start, $end, $pageSize);
    }
}
" filePath="/var/www/html/ProyectoNasa/ProyectoNASA/app/Services/Nasa/ModisExtendedService.php