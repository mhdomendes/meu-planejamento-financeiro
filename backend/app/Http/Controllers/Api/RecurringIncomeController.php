<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPlan;
use App\Models\RecurringIncomeSetting;
use App\Services\MonthlyPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringIncomeController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'normal_income' => $this->setting()->normal_income,
            ],
        ]);
    }

    public function update(
        Request $request,
        MonthlyPlanService $monthlyPlanService
    ): JsonResponse {
        $validated = $request->validate([
            'normal_income' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($validated, $monthlyPlanService): void {
            $setting = $this->setting();
            $setting->update(['normal_income' => $validated['normal_income']]);

            $currentMonth = Carbon::now()->startOfMonth()->toDateString();
            MonthlyPlan::query()
                ->whereDate('month', '>', $currentMonth)
                ->orderBy('month')
                ->get()
                ->each(fn (MonthlyPlan $plan) => $monthlyPlanService->update(
                    $plan,
                    (float) $validated['normal_income'],
                    (float) $plan->extra_income,
                ));
        });

        return response()->json([
            'message' => 'Renda normal recorrente atualizada com sucesso.',
            'data' => [
                'normal_income' => $this->setting()->normal_income,
            ],
        ]);
    }

    private function setting(): RecurringIncomeSetting
    {
        return RecurringIncomeSetting::query()->firstOrCreate(
            ['id' => 1],
            ['normal_income' => 0],
        );
    }
}
