<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MonthlyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m-d'],
        ]);

        $plan = MonthlyPlan::query()
            ->whereDate('month', $validated['month'])
            ->first();

        $expenses = $plan
            ? $plan->expenses()->with(['category', 'monthlyPlan'])->latest()->get()
            : collect();

        return response()->json([
            'data' => $expenses->map(fn (Expense $expense) => $this->serialize($expense)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m-d'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
        ]);

        $plan = MonthlyPlan::query()
            ->whereDate('month', $validated['month'])
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'month' => 'Crie o planejamento do mês antes de cadastrar despesas.',
            ]);
        }

        $hasBudget = $plan->monthlyCategoryBudgets()
            ->where('category_id', $validated['category_id'])
            ->exists();

        if (! $hasBudget) {
            throw ValidationException::withMessages([
                'category_id' => 'A categoria não faz parte do planejamento deste mês.',
            ]);
        }

        $expense = $plan->expenses()->create([
            'category_id' => $validated['category_id'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
        ])->load('category');

        return response()->json([
            'message' => 'Despesa cadastrada com sucesso.',
            'data' => $this->serialize($expense),
        ], 201);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json([
            'message' => 'Despesa removida com sucesso.',
            'data' => null,
        ]);
    }

    private function serialize(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'category_id' => $expense->category_id,
            'category' => $expense->category->name,
            'month' => $expense->monthlyPlan->month->format('Y-m-d'),
            'date' => $expense->created_at->format('Y-m-d'),
            'created_at' => $expense->created_at->toISOString(),
        ];
    }
}
