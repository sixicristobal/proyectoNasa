<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'risk_type',
        'message',
        'data_date',
        'source',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data_date' => 'date',
            'read_at' => 'datetime',
        ];
    }
}
