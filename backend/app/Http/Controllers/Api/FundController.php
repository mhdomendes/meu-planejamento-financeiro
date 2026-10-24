<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BalanceAccount;
use App\Models\BalanceMovement;
use App\Models\PurchaseGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
                    ->sortBy('priority')
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
            'priority' => ['sometimes', 'required', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        $account = BalanceAccount::query()->findOrFail($validated['balance_account_id']);
        $goalCount = $account->purchaseGoals()->count();
        $priority = (int) ($validated['priority'] ?? $goalCount + 1);

        if ($priority > $goalCount + 1) {
            throw ValidationException::withMessages([
                'priority' => 'A prioridade deve estar entre 1 e a próxima posição disponível no fundo.',
            ]);
        }

        $image = $validated['image'] ?? null;
        $imagePath = $image
            ? Storage::disk('public')->putFile('goals', $image)
            : null;

        if ($image && ! $imagePath) {
            throw new RuntimeException('Não foi possível salvar a imagem da meta.');
        }

        try {
            $goal = DB::transaction(function () use ($account, $validated, $priority, $imagePath): PurchaseGoal {
                $lockedAccount = BalanceAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($account->id);

                if ($priority > $lockedAccount->purchaseGoals()->count() + 1) {
                    throw ValidationException::withMessages([
                        'priority' => 'A prioridade selecionada não está mais disponível neste fundo.',
                    ]);
                }

                $lockedAccount->purchaseGoals()
                    ->where('priority', '>=', $priority)
                    ->increment('priority');

                return $lockedAccount->purchaseGoals()->create([
                    'name' => $validated['name'],
                    'target_amount' => $validated['target_amount'],
                    'priority' => $priority,
                    'image_url' => $imagePath,
                ]);
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Meta criada com sucesso.',
            'data' => $this->serializeGoal($goal->load('movements')),
        ], 201);
    }

    public function showGoalImage(PurchaseGoal $goal): StreamedResponse
    {
        abort_unless(
            $goal->image_url && Storage::disk('public')->exists($goal->image_url),
            404
        );

        return Storage::disk('public')->response($goal->image_url);
    }

    public function updateGoal(Request $request, PurchaseGoal $goal): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'priority' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
            'active' => ['sometimes', 'required', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        $image = $validated['image'] ?? null;
        unset($validated['image']);
        $newImagePath = $image
            ? Storage::disk('public')->putFile('goals', $image)
            : null;

        if ($image && ! $newImagePath) {
            throw new RuntimeException('Não foi possível salvar a imagem da meta.');
        }

        $oldImagePath = $goal->image_url;

        try {
            DB::transaction(function () use ($goal, $validated, $newImagePath): void {
                BalanceAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($goal->balance_account_id);
                $goal->refresh();

                if (isset($validated['priority'])) {
                    $newPriority = (int) $validated['priority'];
                    $goalCount = PurchaseGoal::query()
                        ->where('balance_account_id', $goal->balance_account_id)
                        ->count();

                    if ($newPriority > $goalCount) {
                        throw ValidationException::withMessages([
                            'priority' => 'A prioridade deve corresponder a uma posição existente neste fundo.',
                        ]);
                    }
                } else {
                    $newPriority = $goal->priority;
                }

                if ($newPriority !== $goal->priority) {
                    $accountGoals = PurchaseGoal::query()
                        ->where('balance_account_id', $goal->balance_account_id);

                    if ($newPriority < $goal->priority) {
                        $accountGoals
                            ->where('priority', '>=', $newPriority)
                            ->where('priority', '<', $goal->priority)
                            ->increment('priority');
                    } else {
                        $accountGoals
                            ->where('priority', '>', $goal->priority)
                            ->where('priority', '<=', $newPriority)
                            ->decrement('priority');
                    }
                }

                $goal->update([
                    ...$validated,
                    'priority' => $newPriority,
                    ...($newImagePath ? ['image_url' => $newImagePath] : []),
                ]);
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $oldImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

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
            'priority' => $goal->priority,
            'image_url' => $goal->image_url
                ? "/api/goals/{$goal->id}/image"
                : null,
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
