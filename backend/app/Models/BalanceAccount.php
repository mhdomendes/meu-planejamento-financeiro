<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BalanceAccount extends Model
{
    protected $fillable = [
        'name',
        'initial_balance',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BalanceMovement::class);
    }

    public function purchaseGoals(): HasMany
    {
        return $this->hasMany(PurchaseGoal::class);
    }
}
