<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'normal_percentage',
        'extra_percentage',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'normal_percentage' => 'decimal:2',
            'extra_percentage' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function monthlyCategoryBudgets(): HasMany
    {
        return $this->hasMany(MonthlyCategoryBudget::class);
    }
}

