<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseGoal extends Model
{
    protected $fillable = [
        'name',
        'balance_account_id',
        'target_amount',
        'priority',
        'image_url',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'priority' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BalanceAccount::class, 'balance_account_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BalanceMovement::class);
    }
}
