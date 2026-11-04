<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmerPriority extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'water_conservation_weight',
        'income_weight',
        'soil_health_weight',
        'erosion_weight',
        'market_weight',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'water_conservation_weight' => 'integer',
            'income_weight' => 'integer',
            'soil_health_weight' => 'integer',
            'erosion_weight' => 'integer',
            'market_weight' => 'integer',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
