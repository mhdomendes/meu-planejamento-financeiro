<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MonthlyPlan;
use App\Models\RecurringExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        if (! $plan) {
            return response()->json(['data' => []]);
        }

        $this->createDueExpenses($plan);

        $expenses = $plan->expenses()
            ->with(['category', 'monthlyPlan', 'recurringExpense'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $expenses->map(fn (Expense $expense) => $this->serialize($expense)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateExpense($request);
        $plan = MonthlyPlan::query()
            ->whereDate('month', $validated['month'])
            ->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'month' => 'Crie o planejamento do mês antes de cadastrar despesas.',
            ]);
        }
        $this->ensureCategoryIsBudgeted($plan, (int) $validated['category_id']);

        $expense = DB::transaction(function () use ($validated, $plan): Expense {
            $recurringExpense = $this->createRecurringExpense($validated, $plan);
            $expense = $this->createExpense($plan, $validated, $recurringExpense);

            if ($recurringExpense) {
                $this->createDueExpensesForExistingPlans(
                    $recurringExpense,
                    Carbon::parse($plan->month)->toDateString(),
                );
            }

            return $expense->load(['category', 'monthlyPlan', 'recurringExpense']);
        });

        return response()->json([
            'message' => 'Despesa cadastrada com sucesso.',
            'data' => $this->serialize($expense),
        ], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $this->validateExpense($request, true);
        $expense->load(['monthlyPlan', 'recurringExpense']);
        $plan = $expense->monthlyPlan;
        $this->ensureCategoryIsBudgeted($plan, (int) $validated['category_id']);

        $updated = DB::transaction(function () use ($expense, $plan, $validated): Expense {
            $series = $expense->recurringExpense;
            $type = $validated['recurrence_type'];

            if ($series && $type === 'none') {
                $series->expenses()
                    ->whereHas('monthlyPlan', fn ($query) => $query->whereDate('month', '>', $plan->month))
                    ->delete();
                DB::table('recurring_expense_exclusions')
                    ->where('recurring_expense_id', $series->id)
                    ->where('monthly_plan_id', $plan->id)
                    ->delete();
                $series->update(['active' => false]);
                $expense->recurring_expense_id = null;
                $this->updateExpenseFields($expense, $validated);

                return $expense->fresh()->load(['category', 'monthlyPlan', 'recurringExpense']);
            }

            if ($type === 'none') {
                $this->updateExpenseFields($expense, $validated);

                return $expense->fresh()->load(['category', 'monthlyPlan', 'recurringExpense']);
            }

            if (! $series) {
                $series = $this->createRecurringExpense($validated, $plan);
                $expense->recurring_expense_id = $series->id;
                $this->updateExpenseFields($expense, $validated);
            } else {
                $series->expenses()
                    ->whereHas('monthlyPlan', fn ($query) => $query->whereDate('month', '>=', $plan->month))
                    ->delete();
                $series->update($this->recurringAttributes($validated, $series->starts_on));
            }

            DB::table('recurring_expense_exclusions')
                ->where('recurring_expense_id', $series->id)
                ->where('monthly_plan_id', $plan->id)
                ->delete();

            $this->createDueExpensesForExistingPlans(
                $series,
                Carbon::parse($plan->month)->toDateString(),
            );

            return $plan->expenses()
                ->where('recurring_expense_id', $series->id)
                ->with(['category', 'monthlyPlan', 'recurringExpense'])
                ->firstOrFail();
        });

        return response()->json([
            'message' => 'Despesa atualizada com sucesso.',
            'data' => $this->serialize($updated),
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        DB::transaction(function () use ($expense): void {
            if ($expense->recurring_expense_id !== null) {
                DB::table('recurring_expense_exclusions')->insertOrIgnore([
                    'recurring_expense_id' => $expense->recurring_expense_id,
                    'monthly_plan_id' => $expense->monthly_plan_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $expense->delete();
        });

        return response()->json([
            'message' => 'Despesa removida com sucesso.',
            'data' => null,
        ]);
    }

    private function validateExpense(Request $request, bool $updating = false): array
    {
        return $request->validate([
            ...($updating ? [] : ['month' => ['required', 'date_format:Y-m-d']]),
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'recurrence_type' => [
                $updating ? 'required' : 'sometimes',
                Rule::in(['none', 'recurring', 'installment']),
            ],
            'repeat_every_months' => [
                'required_if:recurrence_type,recurring',
                'nullable',
                Rule::in([1, 3]),
            ],
            'installments_total' => [
                'required_if:recurrence_type,installment',
                'nullable',
                'integer',
                'min:2',
                'max:120',
            ],
        ]);
    }

    private function ensureCategoryIsBudgeted(MonthlyPlan $plan, int $categoryId): void
    {
        if (! $plan->monthlyCategoryBudgets()->where('category_id', $categoryId)->exists()) {
            throw ValidationException::withMessages([
                'category_id' => 'A categoria não faz parte do planejamento deste mês.',
            ]);
        }
    }

    private function createRecurringExpense(array $validated, MonthlyPlan $plan): ?RecurringExpense
    {
        if (($validated['recurrence_type'] ?? 'none') === 'none') {
            return null;
        }

        return RecurringExpense::query()->create(
            $this->recurringAttributes(
                $validated,
                Carbon::parse($plan->month)->startOfMonth()->toDateString(),
            ),
        );
    }

    private function recurringAttributes(array $validated, string $startsOn): array
    {
        $type = $validated['recurrence_type'];
        $interval = $type === 'installment'
            ? 1
            : (int) $validated['repeat_every_months'];
        $installmentsTotal = $type === 'installment'
            ? (int) $validated['installments_total']
            : null;

        $endsOn = $installmentsTotal
            ? Carbon::parse($startsOn)->addMonthsNoOverflow(($installmentsTotal - 1) * $interval)->toDateString()
            : null;

        return [
            'description' => $validated['description'],
            'category_id' => $validated['category_id'],
            'amount' => $validated['amount'],
            'type' => $type,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'repeat_every_months' => $interval,
            'installments_total' => $installmentsTotal,
            'active' => true,
        ];
    }

    private function createExpense(
        MonthlyPlan $plan,
        array $validated,
        ?RecurringExpense $recurringExpense
    ): Expense {
        return $plan->expenses()->create([
            'category_id' => $validated['category_id'],
            'recurring_expense_id' => $recurringExpense?->id,
            'description' => $validated['description'],
            'amount' => $validated['amount'],
        ]);
    }

    private function updateExpenseFields(Expense $expense, array $validated): void
    {
        $expense->fill([
            'category_id' => $validated['category_id'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
        ])->save();
    }

    private function createDueExpenses(MonthlyPlan $plan, ?RecurringExpense $only = null): void
    {
        $month = Carbon::parse($plan->month)->startOfMonth();
        $series = $only
            ? collect([$only])
            : RecurringExpense::query()
                ->where('active', true)
                ->whereDate('starts_on', '<=', $month)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month))
                ->get();

        foreach ($series as $recurringExpense) {
            if (! $this->isDue($recurringExpense, $month)) {
                continue;
            }

            $excluded = DB::table('recurring_expense_exclusions')
                ->where('recurring_expense_id', $recurringExpense->id)
                ->where('monthly_plan_id', $plan->id)
                ->exists();

            if ($excluded) {
                continue;
            }
            $this->ensureCategoryIsBudgeted($plan, $recurringExpense->category_id);

            Expense::query()->firstOrCreate(
                [
                    'monthly_plan_id' => $plan->id,
                    'recurring_expense_id' => $recurringExpense->id,
                ],
                [
                    'category_id' => $recurringExpense->category_id,
                    'description' => $recurringExpense->description,
                    'amount' => $recurringExpense->amount,
                ],
            );
        }
    }

    private function createDueExpensesForExistingPlans(RecurringExpense $series, string $fromMonth): void
    {
        $plans = MonthlyPlan::query()
            ->whereDate('month', '>=', $fromMonth)
            ->when($series->ends_on, fn ($query) => $query->whereDate('month', '<=', $series->ends_on))
            ->orderBy('month')
            ->get();

        foreach ($plans as $plan) {
            $this->createDueExpenses($plan, $series);
        }
    }

    private function isDue(RecurringExpense $series, Carbon $month): bool
    {
        if (! $series->active || $month->lt($series->starts_on->copy()->startOfMonth())) {
            return false;
        }
        if ($series->ends_on && $month->gt($series->ends_on->copy()->startOfMonth())) {
            return false;
        }

        $monthsSinceStart = (($month->year - $series->starts_on->year) * 12)
            + $month->month
            - $series->starts_on->month;

        return $monthsSinceStart % $series->repeat_every_months === 0;
    }

    private function serialize(Expense $expense): array
    {
        $series = $expense->recurringExpense;
        $month = Carbon::parse($expense->monthlyPlan->month)->startOfMonth();
        $monthsSinceStart = $series
            ? (($month->year - $series->starts_on->year) * 12) + $month->month - $series->starts_on->month
            : 0;

        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'category_id' => $expense->category_id,
            'category' => $expense->category->name,
            'month' => $expense->monthlyPlan->month->format('Y-m-d'),
            'date' => $expense->created_at->format('Y-m-d'),
            'created_at' => $expense->created_at->toISOString(),
            'recurrence_type' => $series?->type ?? 'none',
            'repeat_every_months' => $series?->repeat_every_months,
            'installments_total' => $series?->installments_total,
            'installment_number' => $series?->type === 'installment'
                ? intdiv($monthsSinceStart, $series->repeat_every_months) + 1
                : null,
        ];
    }
}
