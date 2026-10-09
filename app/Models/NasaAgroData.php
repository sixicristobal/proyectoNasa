<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NasaAgroData extends Model
{
    use HasFactory;

    protected $table = 'nasa_agro_data';

    protected $fillable = [
        'lat',
        'lon',
        'date',
        'temp',
        'temp_max',
        'temp_min',
        'precip',
        'humidity',
        'wind_speed',
        'solar_radiation',
        'ndvi_value',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lon' => 'decimal:7',
            'date' => 'date',
            'temp' => 'float',
            'temp_max' => 'float',
            'temp_min' => 'float',
            'precip' => 'float',
            'humidity' => 'float',
            'wind_speed' => 'float',
            'solar_radiation' => 'float',
            'ndvi_value' => 'float',
        ];
    }

    /**
     * Filtro por cercanía: evita que una coincidencia decimal exacta
     * deje los paneles vacíos al filtrar por lat/lon.
     */
    public function scopeNear($query, float $lat, float $lon, float $delta = 0.05)
    {
        return $query->whereBetween('lat', [$lat - $delta, $lat + $delta])
            ->whereBetween('lon', [$lon - $delta, $lon + $delta]);
    }

    /**
     * Inserción masiva eficiente según agents.md.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function upsertRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return static::upsert(
            $rows,
            uniqueBy: ['lat', 'lon', 'date'],
            update: ['temp', 'temp_max', 'temp_min', 'precip', 'humidity', 'wind_speed', 'solar_radiation', 'ndvi_value', 'updated_at']
        );
    }
}
