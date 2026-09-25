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
        'precip',
        'humidity',
        'ndvi_value',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lon' => 'decimal:7',
            'date' => 'date',
            'temp' => 'float',
            'precip' => 'float',
            'humidity' => 'float',
            'ndvi_value' => 'float',
        ];
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
            update: ['temp', 'precip', 'humidity', 'ndvi_value', 'updated_at']
        );
    }
}
