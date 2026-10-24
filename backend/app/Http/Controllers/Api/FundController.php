<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BalanceAccount;
use App\Models\BalanceMovement;
use App\Models\PurchaseGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FundController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = BalanceAccount::query()
            ->with(['movements.purchaseGoal', 'purchaseGoals.movements'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $accounts->map(fn (BalanceAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'balance' => $this->accountBalance($account),
                'movements' => $account->movements
                    ->sortByDesc('created_at')
                    ->take(10)
                    ->map(fn (BalanceMovement $movement) => $this->serializeMovement($movement))
                    ->values(),
                'goals' => $account->purchaseGoals
                    ->map(fn (PurchaseGoal $goal) => $this->serializeGoal($goal))
                    ->values(),
            ]),
        ]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:balance_accounts,name'],
            'initial_balance' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        $account = BalanceAccount::query()->create([
            'name' => $validated['name'],
            'initial_balance' => $validated['initial_balance'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Fundo criado com sucesso.',
            'data' => [
                'id' => $account->id,
                'name' => $account->name,
                'balance' => $account->initial_balance,
                'movements' => [],
                'goals' => [],
            ],
        ], 201);
    }

    public function storeMovement(Request $request, BalanceAccount $account): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:deposit,withdrawal'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'purchase_goal_id' => ['nullable', 'integer', 'exists:purchase_goals,id'],
        ]);

        $movement = DB::transaction(function () use ($account, $validated): BalanceMovement {
            $lockedAccount = BalanceAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);
            $goal = isset($validated['purchase_goal_id'])
                ? PurchaseGoal::query()->findOrFail($validated['purchase_goal_id'])
                : null;

            if ($goal && $goal->balance_account_id !== $lockedAccount->id) {
                throw ValidationException::withMessages([
                    'purchase_goal_id' => 'A meta deve pertencer ao fundo selecionado.',
                ]);
            }

            if ($goal && ! $goal->active) {
                throw ValidationException::withMessages([
                    'purchase_goal_id' => 'Não é possível movimentar uma meta arquivada.',
                ]);
            }

            if (
                $validated['type'] === 'withdrawal' &&
                $this->accountBalanceCents($lockedAccount) < $this->toCents($validated['amount'])
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor do resgate excede o saldo disponível no fundo.',
                ]);
            }

            if (
                $goal &&
                $validated['type'] === 'withdrawal' &&
                $this->goalBalanceCents($goal) < $this->toCents($validated['amount'])
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor do resgate excede o saldo guardado para esta meta.',
                ]);
            }

            return $lockedAccount->movements()->create([
                'purchase_goal_id' => $goal?->id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Movimentação registrada com sucesso.',
            'data' => $this->serializeMovement($movement->load('purchaseGoal')),
        ], 201);
    }

    public function storeGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'balance_account_id' => ['required', 'integer', 'exists:balance_accounts,id'],
            'target_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
        ]);

        $goal = PurchaseGoal::query()->create($validated);

        return response()->json([
            'message' => 'Meta criada com sucesso.',
            'data' => $this->serializeGoal($goal->load('movements')),
        ], 201);
    }

    public function updateGoal(Request $request, PurchaseGoal $goal): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'active' => ['sometimes', 'required', 'boolean'],
        ]);

        $goal->update($validated);

        return response()->json([
            'message' => 'Meta atualizada com sucesso.',
            'data' => $this->serializeGoal($goal->load('movements')),
        ]);
    }

    private function accountBalance(BalanceAccount $account): float
    {
        return $this->accountBalanceCents($account) / 100;
    }

    private function goalBalance(PurchaseGoal $goal): float
    {
        return $this->goalBalanceCents($goal) / 100;
    }

    private function accountBalanceCents(BalanceAccount $account): int
    {
        $deposits = $account->movements
            ->where('type', 'deposit')
            ->sum(fn ($movement) => $this->toCents($movement->amount));
        $withdrawals = $account->movements
            ->where('type', 'withdrawal')
            ->sum(fn ($movement) => $this->toCents($movement->amount));

        return $this->toCents($account->initial_balance) + $deposits - $withdrawals;
    }

    private function goalBalanceCents(PurchaseGoal $goal): int
    {
        $deposits = $goal->movements
            ->where('type', 'deposit')
            ->sum(fn ($movement) => $this->toCents($movement->amount));
        $withdrawals = $goal->movements
            ->where('type', 'withdrawal')
            ->sum(fn ($movement) => $this->toCents($movement->amount));

        return $deposits - $withdrawals;
    }

    private function toCents(string|int|float $amount): int
    {
        $formatted = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = explode('.', $formatted, 2);

        return (int) $whole * 100 + (int) $fraction;
    }

    private function serializeGoal(PurchaseGoal $goal): array
    {
        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'balance_account_id' => $goal->balance_account_id,
            'target_amount' => $goal->target_amount,
            'current_amount' => $this->goalBalance($goal),
            'active' => $goal->active,
        ];
    }

    private function serializeMovement(BalanceMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'type' => $movement->type,
            'amount' => $movement->amount,
            'description' => $movement->description,
            'purchase_goal_id' => $movement->purchase_goal_id,
            'purchase_goal' => $movement->purchaseGoal?->name,
            'created_at' => $movement->created_at->toISOString(),
        ];
    }
}
