<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'name',
        'latitude',
        'longitude',
        'area_ha',
        'geometry',
        'soil_type',
        'soil_ph',
        'soil_organic_matter_pct',
        'irrigation_type',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'area_ha' => 'float',
            'geometry' => 'array',
            'soil_ph' => 'float',
            'soil_organic_matter_pct' => 'float',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function priorities(): HasOne
    {
        return $this->hasOne(FarmerPriority::class);
    }

    public function rotations(): HasMany
    {
        return $this->hasMany(Rotation::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}
