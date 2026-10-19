<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyCategoryBudget extends Model
{
    protected $fillable = [
        'monthly_plan_id',
        'category_id',
        'normal_percentage',
        'extra_percentage',
        'normal_amount',
        'extra_amount',
        'total_budget',
    ];

    protected function casts(): array
    {
        return [
            'normal_percentage' => 'decimal:2',
            'extra_percentage' => 'decimal:2',
            'normal_amount' => 'decimal:2',
            'extra_amount' => 'decimal:2',
            'total_budget' => 'decimal:2',
        ];
    }

    public function monthlyPlan(): BelongsTo
    {
        return $this->belongsTo(MonthlyPlan::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
