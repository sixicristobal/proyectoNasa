<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotationStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'rotation_id',
        'crop_id',
        'sequence',
        'season',
        'duration_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'duration_days' => 'integer',
        ];
    }

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(Rotation::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }
}
