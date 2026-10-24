<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPlan;
use App\Models\RecurringIncomeSetting;
use App\Services\MonthlyPlanService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MonthlyPlanController extends Controller
{
    public function store(
        Request $request,
        MonthlyPlanService $monthlyPlanService
    ): JsonResponse {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m-d'],
            'normal_income' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'extra_income' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        $plan = $monthlyPlanService->create(
            $validated['month'],
            (float) ($validated['normal_income'] ?? RecurringIncomeSetting::query()->value('normal_income') ?? 0),
            (float) $validated['extra_income']
        );

        return response()->json([
            'message' => 'Planejamento mensal criado com sucesso.',
            'data' => $this->serializePlan($plan),
        ], 201);
    }

    public function ensure(
        Request $request,
        MonthlyPlanService $monthlyPlanService
    ): JsonResponse {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m-d'],
        ]);
        $month = Carbon::parse($validated['month'])->startOfMonth()->toDateString();
        $plan = MonthlyPlan::query()
            ->with('monthlyCategoryBudgets.category')
            ->whereDate('month', $month)
            ->first();

        if (! $plan) {
            try {
                $plan = $monthlyPlanService->create(
                    $month,
                    (float) (RecurringIncomeSetting::query()->value('normal_income') ?? 0),
                    0,
                );
            } catch (QueryException $exception) {
                $plan = MonthlyPlan::query()
                    ->with('monthlyCategoryBudgets.category')
                    ->whereDate('month', $month)
                    ->first();

                if (! $plan) {
                    throw $exception;
                }
            }
        }

        return response()->json([
            'message' => 'Planejamento mensal disponível.',
            'data' => $this->serializePlan($plan),
        ]);
    }

    public function update(
        Request $request,
        MonthlyPlan $plan,
        MonthlyPlanService $monthlyPlanService
    ): JsonResponse {
        $validated = $request->validate([
            'normal_income' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'extra_income' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        $plan = $monthlyPlanService->update(
            $plan,
            (float) $validated['normal_income'],
            (float) $validated['extra_income'],
        );

        return response()->json([
            'message' => 'Planejamento mensal atualizado com sucesso.',
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
            'data' => $this->serializePlan($plan),
        ]);
    }

    private function serializePlan(MonthlyPlan $plan): array
    {
        return [
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
            )->values()->all(),
        ];
    }
}
