<?php

namespace App\Console\Commands;

use App\Models\BalanceMovement;
use App\Models\PurchaseGoal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClearExistingGoals extends Command
{
    protected $signature = 'goals:clear-existing';

    protected $description = 'Remove metas existentes sem apagar o histórico de movimentações';

    public function handle(): int
    {
        $goals = PurchaseGoal::query()->get(['id', 'image_url']);

        if ($goals->isEmpty()) {
            $this->info('Não há metas cadastradas para remover.');

            return self::SUCCESS;
        }

        $linkedMovements = BalanceMovement::query()
            ->whereIn('purchase_goal_id', $goals->pluck('id'))
            ->count();

        $this->warn(sprintf(
            'Serão removidas %d meta(s). %d movimentação(ões) serão preservadas sem vínculo com meta.',
            $goals->count(),
            $linkedMovements,
        ));

        if (! $this->confirm('Confirma a remoção de todas as metas existentes?', false)) {
            $this->info('Operação cancelada. Nenhuma meta foi removida.');

            return self::SUCCESS;
        }

        $imagePaths = $goals
            ->pluck('image_url')
            ->filter(fn (?string $path): bool => $this->isStoredGoalImage($path))
            ->unique()
            ->values();

        DB::transaction(function () use ($goals): void {
            BalanceMovement::query()
                ->whereIn('purchase_goal_id', $goals->pluck('id'))
                ->update(['purchase_goal_id' => null]);

            PurchaseGoal::query()
                ->whereIn('id', $goals->pluck('id'))
                ->delete();
        });

        $failedDeletions = $imagePaths
            ->reject(fn (string $path): bool => Storage::disk('public')->delete($path));

        if ($failedDeletions->isNotEmpty()) {
            $this->error('As metas foram removidas, mas não foi possível excluir estas imagens:');
            $failedDeletions->each(fn (string $path) => $this->line($path));

            return self::FAILURE;
        }

        $this->info('Metas removidas. O histórico de movimentações foi preservado.');

        return self::SUCCESS;
    }

    private function isStoredGoalImage(?string $path): bool
    {
        return $path !== null
            && str_starts_with($path, 'goals/')
            && basename($path) === substr($path, strlen('goals/'));
    }
}
