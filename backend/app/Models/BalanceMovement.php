<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceMovement extends Model
{
    protected $fillable = [
        'balance_account_id',
        'purchase_goal_id',
        'monthly_category_budget_id',
        'type',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BalanceAccount::class, 'balance_account_id');
    }

    public function purchaseGoal(): BelongsTo
    {
        return $this->belongsTo(PurchaseGoal::class);
    }
}
