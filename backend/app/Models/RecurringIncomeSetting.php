<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringIncomeSetting extends Model
{
    protected $fillable = [
        'normal_income',
    ];

    protected function casts(): array
    {
        return [
            'normal_income' => 'decimal:2',
        ];
    }
}
