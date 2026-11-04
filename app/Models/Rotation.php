<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'name',
        'is_current',
        'description',
        'strategy_type',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RotationStep::class)->orderBy('sequence');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}
