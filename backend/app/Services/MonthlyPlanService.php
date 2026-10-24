<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MonthlyPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyPlanService
{
    public function create(
        string $month,
        float $normalIncome,
        float $extraIncome
    ): MonthlyPlan {
        if ($normalIncome < 0 || $extraIncome < 0) {
            throw ValidationException::withMessages([
                'income' => 'As entradas não podem ser negativas.',
            ]);
        }

        return DB::transaction(function () use (
            $month,
            $normalIncome,
            $extraIncome
        ) {
            $monthDate = \Carbon\Carbon::parse($month)
                ->startOfMonth()
                ->toDateString();

            if (MonthlyPlan::whereDate('month', $monthDate)->exists()) {
                throw ValidationException::withMessages([
                    'month' => 'Já existe um planejamento para esse mês.',
                ]);
            }

            $categories = Category::where('active', true)
                ->orderBy('id')
                ->get();

            if ($categories->isEmpty()) {
                throw ValidationException::withMessages([
                    'categories' => 'Cadastre ao menos uma categoria ativa.',
                ]);
            }

            $normalPercentageTotal = round(
                (float) $categories->sum('normal_percentage'),
                2
            );

            $extraPercentageTotal = round(
                (float) $categories->sum('extra_percentage'),
                2
            );

            if (
                $normalPercentageTotal !== 100.00 ||
                $extraPercentageTotal !== 100.00
            ) {
                throw ValidationException::withMessages([
                    'percentages' =>
                        'Os percentuais das categorias ativas devem somar 100% para cada tipo de entrada.',
                ]);
            }

            $plan = MonthlyPlan::create([
                'month' => $monthDate,
                'normal_income' => $normalIncome,
                'extra_income' => $extraIncome,
            ]);

            $normalAllocated = 0;
            $extraAllocated = 0;
            $lastIndex = $categories->count() - 1;

            foreach ($categories as $index => $category) {
                if ($index === $lastIndex) {
                    // Ajusta os centavos restantes para fechar os totais.
                    $normalAmount = round(
                        $normalIncome - $normalAllocated,
                        2
                    );

                    $extraAmount = round(
                        $extraIncome - $extraAllocated,
                        2
                    );
                } else {
                    $normalAmount = round(
                        $normalIncome *
                        (float) $category->normal_percentage / 100,
                        2
                    );

                    $extraAmount = round(
                        $extraIncome *
                        (float) $category->extra_percentage / 100,
                        2
                    );

                    $normalAllocated += $normalAmount;
                    $extraAllocated += $extraAmount;
                }

                $plan->monthlyCategoryBudgets()->create([
                    'category_id' => $category->id,
                    'normal_percentage' => $category->normal_percentage,
                    'extra_percentage' => $category->extra_percentage,
                    'normal_amount' => $normalAmount,
                    'extra_amount' => $extraAmount,
                    'total_budget' => round(
                        $normalAmount + $extraAmount,
                        2
                    ),
                ]);
            }

            return $plan->load('monthlyCategoryBudgets.category');
        });
    }

    public function update(
        MonthlyPlan $plan,
        float $normalIncome,
        float $extraIncome
    ): MonthlyPlan {
        if ($normalIncome < 0 || $extraIncome < 0) {
            throw ValidationException::withMessages([
                'income' => 'As entradas não podem ser negativas.',
            ]);
        }

        return DB::transaction(function () use ($plan, $normalIncome, $extraIncome): MonthlyPlan {
            $plan = MonthlyPlan::query()
                ->lockForUpdate()
                ->findOrFail($plan->id);
            $budgets = $plan->monthlyCategoryBudgets()
                ->orderBy('id')
                ->get();

            $normalAllocated = 0;
            $extraAllocated = 0;
            $lastIndex = $budgets->count() - 1;

            foreach ($budgets as $index => $budget) {
                if ($index === $lastIndex) {
                    $normalAmount = round($normalIncome - $normalAllocated, 2);
                    $extraAmount = round($extraIncome - $extraAllocated, 2);
                } else {
                    $normalAmount = round(
                        $normalIncome * (float) $budget->normal_percentage / 100,
                        2,
                    );
                    $extraAmount = round(
                        $extraIncome * (float) $budget->extra_percentage / 100,
                        2,
                    );
                    $normalAllocated += $normalAmount;
                    $extraAllocated += $extraAmount;
                }

                $budget->update([
                    'normal_amount' => $normalAmount,
                    'extra_amount' => $extraAmount,
                    'total_budget' => round($normalAmount + $extraAmount, 2),
                ]);
            }

            $plan->update([
                'normal_income' => $normalIncome,
                'extra_income' => $extraIncome,
            ]);

            return $plan->load('monthlyCategoryBudgets.category');
        });
    }
}
