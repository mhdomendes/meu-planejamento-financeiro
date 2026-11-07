<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InvestmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Investment::query()
                ->orderBy('ticker')
                ->get()
                ->map(fn (Investment $investment) => $this->serialize($investment)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $logo = $validated['logo'] ?? null;
        unset($validated['logo']);

        $logoPath = $logo
            ? Storage::disk('public')->putFile('investments', $logo)
            : null;

        if ($logo && ! $logoPath) {
            throw new RuntimeException('Não foi possível salvar o logo do ativo.');
        }

        try {
            $investment = Investment::query()->create([
                ...$validated,
                'logo_path' => $logoPath,
            ]);
        } catch (Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Ativo cadastrado com sucesso.',
            'data' => $this->serialize($investment),
        ], 201);
    }

    public function update(Request $request, Investment $investment): JsonResponse
    {
        $validated = $request->validate($this->rules(true));
        $logo = $validated['logo'] ?? null;
        unset($validated['logo']);

        $newLogoPath = $logo
            ? Storage::disk('public')->putFile('investments', $logo)
            : null;

        if ($logo && ! $newLogoPath) {
            throw new RuntimeException('Não foi possível salvar o logo do ativo.');
        }

        $oldLogoPath = $investment->logo_path;

        try {
            $investment->update([
                ...$validated,
                ...($newLogoPath ? ['logo_path' => $newLogoPath] : []),
            ]);
        } catch (Throwable $exception) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($newLogoPath && $oldLogoPath && ! Storage::disk('public')->delete($oldLogoPath)) {
            throw new RuntimeException('O ativo foi atualizado, mas não foi possível remover o logo anterior.');
        }

        return response()->json([
            'message' => 'Ativo atualizado com sucesso.',
            'data' => $this->serialize($investment->fresh()),
        ]);
    }

    public function destroy(Investment $investment): JsonResponse
    {
        $logoPath = $investment->logo_path;
        $investment->delete();

        if ($logoPath && ! Storage::disk('public')->delete($logoPath)) {
            throw new RuntimeException('O ativo foi removido, mas não foi possível remover seu logo.');
        }

        return response()->json([
            'message' => 'Ativo removido com sucesso.',
            'data' => null,
        ]);
    }

    public function showLogo(Investment $investment): StreamedResponse
    {
        abort_unless(
            $investment->logo_path && Storage::disk('public')->exists($investment->logo_path),
            404,
        );

        return Storage::disk('public')->response($investment->logo_path);
    }

    private function rules(bool $updating = false): array
    {
        return [
            'ticker' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(['acoes', 'caixa', 'stock', 'btc'])],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999999999.99999999', 'decimal:0,8'],
            'quote_value' => ['required', 'numeric', 'min:0', 'max:99999999999999.9999', 'decimal:0,4'],
            'ideal_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'logo' => [
                ...($updating ? ['sometimes'] : ['nullable']),
                'image',
                'mimes:jpeg,png,webp,gif',
                'max:5120',
            ],
        ];
    }

    private function serialize(Investment $investment): array
    {
        return [
            'id' => $investment->id,
            'ticker' => $investment->ticker,
            'type' => $investment->type,
            'quantity' => $investment->quantity,
            'quote_value' => $investment->quote_value,
            'ideal_percentage' => $investment->ideal_percentage,
            'logo_url' => $investment->logo_path
                ? "/api/investments/{$investment->id}/logo"
                : null,
        ];
    }
}
