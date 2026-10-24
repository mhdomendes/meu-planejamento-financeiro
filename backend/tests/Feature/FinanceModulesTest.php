<?php

namespace Tests\Feature;

use App\Models\BalanceAccount;
use App\Models\Category;
use App\Services\MonthlyPlanService;
use Database\Seeders\BalanceAccountSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CategorySeeder::class,
            BalanceAccountSeeder::class,
        ]);
    }

    public function test_it_creates_lists_and_deletes_monthly_expenses(): void
    {
        app(MonthlyPlanService::class)->create('2026-11-01', 4500, 500);
        $category = Category::query()->where('name', 'Despesas essenciais')->firstOrFail();

        $created = $this->postJson('/api/expenses', [
            'month' => '2026-11-01',
            'category_id' => $category->id,
            'description' => 'Conta de energia',
            'amount' => 145.50,
        ])->assertCreated()
            ->assertJsonPath('data.category', 'Despesas essenciais')
            ->assertJsonPath('data.amount', '145.50');

        $expenseId = $created->json('data.id');

        $this->getJson('/api/expenses?month=2026-11-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Conta de energia');

        $this->deleteJson("/api/expenses/{$expenseId}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    public function test_it_creates_and_fetches_a_monthly_plan_through_the_api(): void
    {
        $this->postJson('/api/monthly-plans', [
            'month' => '2026-11-01',
            'normal_income' => 4500,
            'extra_income' => 500,
        ])->assertCreated()
            ->assertJsonPath('data.month', '2026-11-01')
            ->assertJsonCount(3, 'data.budgets');

        $this->getJson('/api/monthly-plans/2026-11-01')
            ->assertOk()
            ->assertJsonPath('data.normal_income', '4500.00');
    }

    public function test_it_returns_null_when_a_monthly_plan_does_not_exist(): void
    {
        $this->getJson('/api/monthly-plans/2026-10-01')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_it_rejects_expenses_for_categories_outside_the_plan(): void
    {
        app(MonthlyPlanService::class)->create('2026-11-01', 4500, 500);
        $inactiveCategory = Category::query()->create([
            'name' => 'Inativa',
            'normal_percentage' => 0,
            'extra_percentage' => 0,
            'active' => false,
        ]);

        $this->postJson('/api/expenses', [
            'month' => '2026-11-01',
            'category_id' => $inactiveCategory->id,
            'description' => 'Teste',
            'amount' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    public function test_it_updates_category_distribution_only_when_totals_are_one_hundred(): void
    {
        $categories = Category::query()->where('active', true)->orderBy('id')->get();
        $payload = $categories->map(fn (Category $category) => [
            'id' => $category->id,
            'normal_percentage' => $category->name === 'Despesas essenciais' ? 50 : $category->normal_percentage,
            'extra_percentage' => $category->extra_percentage,
        ])->all();

        $this->putJson('/api/categories/distribution', ['categories' => $payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categories');

        $payload[0]['normal_percentage'] = 45;

        $this->putJson('/api/categories/distribution', ['categories' => $payload])
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_it_tracks_fund_and_goal_balances_and_prevents_overspending(): void
    {
        $account = BalanceAccount::query()->firstOrFail();
        $goal = $this->postJson('/api/goals', [
            'name' => 'Viagem',
            'balance_account_id' => $account->id,
            'target_amount' => 1000,
        ])->assertCreated()
            ->assertJsonPath('data.current_amount', 0)
            ->json('data.id');

        $this->postJson("/api/funds/{$account->id}/movements", [
            'type' => 'deposit',
            'amount' => 250,
            'purchase_goal_id' => $goal,
        ])->assertCreated();

        $this->postJson("/api/funds/{$account->id}/movements", [
            'type' => 'withdrawal',
            'amount' => 300,
            'purchase_goal_id' => $goal,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->getJson('/api/funds')
            ->assertOk()
            ->assertJsonPath('data.0.goals.0.current_amount', 250)
            ->assertJsonPath('data.0.balance', 250);
    }
}
