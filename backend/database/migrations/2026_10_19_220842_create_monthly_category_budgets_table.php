<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_category_budgets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monthly_plan_id')
                ->constrained('monthly_plans')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->decimal('normal_percentage', 5, 2)->default(0);
            $table->decimal('extra_percentage', 5, 2)->default(0);

            $table->decimal('normal_amount', 12, 2)->default(0);
            $table->decimal('extra_amount', 12, 2)->default(0);
            $table->decimal('total_budget', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['monthly_plan_id', 'category_id'],
                'monthly_category_budgets_plan_category_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_category_budgets');
    }
};