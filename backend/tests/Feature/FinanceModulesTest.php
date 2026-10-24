<?php

namespace Tests\Feature;

use App\Models\BalanceAccount;
use App\Models\BalanceMovement;
use App\Models\Category;
use App\Models\PurchaseGoal;
use App\Services\MonthlyPlanService;
use Database\Seeders\BalanceAccountSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
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

    public function test_it_updates_monthly_plan_income_and_recalculates_category_budgets(): void
    {
        $plan = app(MonthlyPlanService::class)->create('2026-11-01', 4500, 500);

        $this->patchJson("/api/monthly-plans/{$plan->id}", [
            'normal_income' => 6000,
            'extra_income' => 1000,
        ])->assertOk()
            ->assertJsonPath('data.normal_income', '6000.00')
            ->assertJsonPath('data.extra_income', '1000.00');

        $this->assertEquals(
            7000.00,
            (float) $plan->fresh()->monthlyCategoryBudgets->sum('total_budget'),
        );
    }

    public function test_recurring_normal_income_updates_future_plans_and_defaults_new_plans(): void
    {
        Carbon::setTestNow('2026-10-24 12:00:00');
        try {
            $past = app(MonthlyPlanService::class)->create('2026-09-01', 4000, 100);
            $current = app(MonthlyPlanService::class)->create('2026-10-01', 4500, 200);
            $future = app(MonthlyPlanService::class)->create('2026-11-01', 5000, 300);

            $this->putJson('/api/recurring-income', [
                'normal_income' => 5200,
            ])->assertOk()
                ->assertJsonPath('data.normal_income', '5200.00');

            $this->assertEquals('4000.00', $past->fresh()->normal_income);
            $this->assertEquals('4500.00', $current->fresh()->normal_income);
            $this->assertEquals('5200.00', $future->fresh()->normal_income);
            $this->assertEquals('300.00', $future->fresh()->extra_income);

            $this->postJson('/api/monthly-plans', [
                'month' => '2026-12-01',
                'extra_income' => 0,
            ])->assertCreated()
                ->assertJsonPath('data.normal_income', '5200.00');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_automatically_creates_a_monthly_plan_using_recurring_income(): void
    {
        $this->putJson('/api/recurring-income', [
            'normal_income' => 4800,
        ])->assertOk();

        $firstResponse = $this->postJson('/api/monthly-plans/ensure', [
            'month' => '2026-12-01',
        ])->assertOk()
            ->assertJsonPath('data.normal_income', '4800.00')
            ->assertJsonPath('data.extra_income', '0.00');

        $this->postJson('/api/monthly-plans/ensure', [
            'month' => '2026-12-01',
        ])->assertOk()
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));

        $this->assertDatabaseCount('monthly_plans', 1);
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

    public function test_it_orders_goals_within_a_fund_and_uploads_goal_images(): void
    {
        Storage::fake('public');
        $account = BalanceAccount::query()->firstOrFail();

        $firstGoalId = $this->postJson('/api/goals', [
            'name' => 'Reserva',
            'balance_account_id' => $account->id,
            'target_amount' => 1000,
        ])->assertCreated()
            ->assertJsonPath('data.priority', 1)
            ->json('data.id');

        $secondGoal = $this->post('/api/goals', [
            'name' => 'Viagem',
            'balance_account_id' => $account->id,
            'target_amount' => 2500,
            'priority' => 1,
            'image' => UploadedFile::fake()->createWithContent(
                'viagem.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p6kAAAAASUVORK5CYII=')
            ),
        ])->assertCreated();

        $secondGoalId = $secondGoal->json('data.id');
        $imagePath = PurchaseGoal::query()->findOrFail($secondGoalId)->image_url;

        $secondGoal->assertJsonPath('data.priority', 1)
            ->assertJsonPath('data.image_url', "/api/goals/{$secondGoalId}/image");
        Storage::disk('public')->assertExists($imagePath);
        $this->get("/api/goals/{$secondGoalId}/image")->assertOk();

        $this->assertDatabaseHas('purchase_goals', [
            'id' => $firstGoalId,
            'priority' => 2,
        ]);

        $this->patchJson("/api/goals/{$secondGoalId}", ['priority' => 2])
            ->assertOk()
            ->assertJsonPath('data.priority', 2);

        $this->assertDatabaseHas('purchase_goals', [
            'id' => $firstGoalId,
            'priority' => 1,
        ]);
    }

    public function test_it_clears_existing_goals_without_deleting_the_movement_history(): void
    {
        Storage::fake('public');
        $account = BalanceAccount::query()->firstOrFail();
        $goal = PurchaseGoal::query()->create([
            'name' => 'Viagem',
            'balance_account_id' => $account->id,
            'target_amount' => 2500,
            'priority' => 1,
            'image_url' => 'goals/viagem.png',
        ]);
        Storage::disk('public')->put('goals/viagem.png', 'image');
        $movement = BalanceMovement::query()->create([
            'balance_account_id' => $account->id,
            'purchase_goal_id' => $goal->id,
            'type' => 'deposit',
            'amount' => 100,
        ]);

        $this->artisan('goals:clear-existing')
            ->expectsConfirmation('Confirma a remoção de todas as metas existentes?', 'yes')
            ->expectsOutput('Metas removidas. O histórico de movimentações foi preservado.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('purchase_goals', ['id' => $goal->id]);
        $this->assertDatabaseHas('balance_movements', [
            'id' => $movement->id,
            'purchase_goal_id' => null,
            'amount' => 100,
        ]);
        Storage::disk('public')->assertMissing('goals/viagem.png');
    }
}
