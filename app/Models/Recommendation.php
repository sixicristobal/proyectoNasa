<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'rotation_id',
        'rank',
        'why_suggested',
        'evidence',
        'benefits',
        'risks',
        'information_gaps',
        'confidence',
        'requires_local_validation',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'confidence' => 'float',
            'requires_local_validation' => 'boolean',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(Rotation::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
