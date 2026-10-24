<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\MonthlyPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MonthlyPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create([
            'name' => 'Despesas essenciais',
            'normal_percentage' => 45,
            'extra_percentage' => 45,
            'active' => true,
        ]);

        Category::create([
            'name' => 'Estilo de vida',
            'normal_percentage' => 25,
            'extra_percentage' => 25,
            'active' => true,
        ]);

        Category::create([
            'name' => 'Investimentos',
            'normal_percentage' => 30,
            'extra_percentage' => 30,
            'active' => true,
        ]);
    }

    public function test_it_distributes_normal_and_extra_income(): void
    {
        $plan = app(MonthlyPlanService::class)->create(
            '2026-11-01',
            4500,
            500
        );

        $this->assertDatabaseHas('monthly_plans', [
            'month' => '2026-11-01 00:00:00',
            'normal_income' => 4500,
            'extra_income' => 500,
        ]);

        $this->assertEquals(
            5000.00,
            (float) $plan->monthlyCategoryBudgets->sum('total_budget')
        );

        $essentialsBudget = $plan->monthlyCategoryBudgets
            ->first(fn ($budget) => $budget->category->name === 'Despesas essenciais');

        $this->assertNotNull($essentialsBudget);
        $this->assertEquals(2250.00, (float) $essentialsBudget->total_budget);
    }

    public function test_it_rejects_duplicate_monthly_plans(): void
    {
        $service = app(MonthlyPlanService::class);

        $service->create('2026-11-01', 4500, 500);

        $this->expectException(ValidationException::class);

        $service->create('2026-11-01', 4500, 500);
    }

    public function test_it_rejects_invalid_percentage_totals(): void
    {
        Category::where('name', 'Investimentos')->update([
            'normal_percentage' => 20,
        ]);

        $this->expectException(ValidationException::class);

        app(MonthlyPlanService::class)->create(
            '2026-12-01',
            4500,
            500
        );
    }
}
