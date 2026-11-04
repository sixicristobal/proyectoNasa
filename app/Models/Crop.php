<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'scientific_name',
        'family',
        'nitrogen_fixing',
        'deep_root',
        'drought_tolerant',
        'water_demand',
        'growing_days',
        'planting_months',
        'harvest_months',
        'market_value',
        'forage_value',
        'erosion_control',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'nitrogen_fixing' => 'boolean',
            'deep_root' => 'boolean',
            'drought_tolerant' => 'boolean',
            'growing_days' => 'integer',
            'planting_months' => 'array',
            'harvest_months' => 'array',
        ];
    }

    public function rotationSteps(): HasMany
    {
        return $this->hasMany(RotationStep::class);
    }
}
