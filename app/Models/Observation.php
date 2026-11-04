<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observation extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'source',
        'variable',
        'value',
        'unit',
        'observed_at',
        'raw_response',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'observed_at' => 'date',
            'raw_response' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    /**
     * Inserta o actualiza observaciones de forma idempotente.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function upsertRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $rows = array_map(function (array $row): array {
            if (isset($row['raw_response']) && is_array($row['raw_response'])) {
                $row['raw_response'] = json_encode(
                    $row['raw_response'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }

            if (isset($row['observed_at']) && $row['observed_at'] instanceof \DateTimeInterface) {
                $row['observed_at'] = $row['observed_at']->format('Y-m-d');
            }

            if (isset($row['fetched_at']) && $row['fetched_at'] instanceof \DateTimeInterface) {
                $row['fetched_at'] = $row['fetched_at']->format('Y-m-d H:i:s');
            }

            return $row;
        }, $rows);

        return static::upsert(
            $rows,
            uniqueBy: ['field_id', 'source', 'variable', 'observed_at'],
            update: ['value', 'unit', 'raw_response', 'fetched_at', 'updated_at']
        );
    }
}
