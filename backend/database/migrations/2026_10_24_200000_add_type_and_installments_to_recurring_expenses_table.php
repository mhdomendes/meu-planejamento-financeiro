<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->string('type')->default('recurring')->after('amount');
            $table->unsignedSmallInteger('installments_total')->nullable()->after('repeat_every_months');
        });

        Schema::create('recurring_expense_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')
                ->constrained('recurring_expenses')
                ->cascadeOnDelete();
            $table->foreignId('monthly_plan_id')
                ->constrained('monthly_plans')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['recurring_expense_id', 'monthly_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_exclusions');

        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->dropColumn(['type', 'installments_total']);
        });
    }
};
