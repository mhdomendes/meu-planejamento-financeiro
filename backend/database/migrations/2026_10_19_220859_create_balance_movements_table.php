<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('balance_account_id')
                ->constrained('balance_accounts')
                ->restrictOnDelete();

            $table->foreignId('monthly_category_budget_id')
                ->nullable()
                ->constrained('monthly_category_budgets')
                ->restrictOnDelete();

            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();

            $table->timestamps();

            $table->unique(
                'monthly_category_budget_id',
                'balance_movements_budget_unique'
            );

            $table->index(['balance_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_movements');
    }
};