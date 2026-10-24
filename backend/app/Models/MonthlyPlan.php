<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyPlan extends Model
{
    protected $fillable = [
        'month',
        'normal_income',
        'extra_income',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'normal_income' => 'decimal:2',
            'extra_income' => 'decimal:2',
        ];
    }

    public function monthlyCategoryBudgets(): HasMany
    {
        return $this->hasMany(MonthlyCategoryBudget::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
