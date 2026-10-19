<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monthly_plan_id')
                ->constrained('monthly_plans')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->foreignId('recurring_expense_id')
                ->nullable()
                ->constrained('recurring_expenses')
                ->nullOnDelete();

            $table->string('description');
            $table->decimal('amount', 12, 2);

            $table->timestamps();

            $table->unique(
                ['monthly_plan_id', 'recurring_expense_id'],
                'expenses_plan_recurring_unique'
            );

            $table->index(['monthly_plan_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};