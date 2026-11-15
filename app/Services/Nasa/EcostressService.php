<?php

namespace App\Services\Nasa;

use App\Models\Field;
use Illuminate\Support\Carbon;

class EcostressService
{
    public function __construct(protected CmrService $cmr) {}

    public function fetch(Field $field, Carbon $start, Carbon $end, string $shortName = 'ECO_L2T_LSTE', int $pageSize = 10): array
    {
        return $this->cmr->searchGranules($shortName, (float) $field->latitude, (float) $field->longitude, $start, $end, $pageSize);
    }
}
