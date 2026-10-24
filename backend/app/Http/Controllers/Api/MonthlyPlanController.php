<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPlan;
use App\Services\MonthlyPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthlyPlanController extends Controller
{
    public function store(
        Request $request,
        MonthlyPlanService $monthlyPlanService
    ): JsonResponse {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m-d'],
            'normal_income' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'extra_income' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ]);

        $plan = $monthlyPlanService->create(
            $validated['month'],
            (float) $validated['normal_income'],
            (float) $validated['extra_income']
        );

        return response()->json([
            'message' => 'Planejamento mensal criado com sucesso.',
            'data' => [
                'id' => $plan->id,
                'month' => $plan->month->format('Y-m-d'),
                'normal_income' => $plan->normal_income,
                'extra_income' => $plan->extra_income,
                'budgets' => $plan->monthlyCategoryBudgets->map(
                    fn ($budget) => [
                        'category' => $budget->category->name,
                        'normal_percentage' => $budget->normal_percentage,
                        'extra_percentage' => $budget->extra_percentage,
                        'normal_amount' => $budget->normal_amount,
                        'extra_amount' => $budget->extra_amount,
                        'total_budget' => $budget->total_budget,
                    ]
                )->values(),
            ],
        ], 201);
    }

    public function show(string $month): JsonResponse
    {
        validator(
            ['month' => $month],
            ['month' => ['required', 'date_format:Y-m-d']]
        )->validate();

        $plan = MonthlyPlan::query()
            ->with('monthlyCategoryBudgets.category')
            ->whereDate('month', $month)
            ->first();

        if (! $plan) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $plan->id,
                'month' => $plan->month->format('Y-m-d'),
                'normal_income' => $plan->normal_income,
                'extra_income' => $plan->extra_income,
                'budgets' => $plan->monthlyCategoryBudgets->map(
                    fn ($budget) => [
                        'category' => $budget->category->name,
                        'normal_percentage' => $budget->normal_percentage,
                        'extra_percentage' => $budget->extra_percentage,
                        'normal_amount' => $budget->normal_amount,
                        'extra_amount' => $budget->extra_amount,
                        'total_budget' => $budget->total_budget,
                    ]
                )->values(),
            ],
        ]);
    }
}
