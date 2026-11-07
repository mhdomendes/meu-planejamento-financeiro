<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    protected $fillable = [
        'ticker',
        'type',
        'logo_path',
        'quantity',
        'quote_value',
        'ideal_percentage',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'quote_value' => 'decimal:4',
            'ideal_percentage' => 'decimal:2',
        ];
    }
}
