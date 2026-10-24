<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()
                ->orderBy('id')
                ->get()
                ->map(fn (Category $category) => $this->serialize($category)),
        ]);
    }

    public function updateDistribution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.id' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'categories.*.normal_percentage' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'categories.*.extra_percentage' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
        ]);

        $categories = Category::query()->where('active', true)->orderBy('id')->get();
        $submitted = collect($validated['categories'])->keyBy('id');

        if (
            $categories->count() !== $submitted->count() ||
            $categories->contains(fn (Category $category) => ! $submitted->has($category->id))
        ) {
            throw ValidationException::withMessages([
                'categories' => 'Envie exatamente todas as categorias ativas.',
            ]);
        }

        $normalTotal = round(
            collect($validated['categories'])->sum('normal_percentage'),
            2
        );
        $extraTotal = round(
            collect($validated['categories'])->sum('extra_percentage'),
            2
        );

        if ($normalTotal !== 100.0 || $extraTotal !== 100.0) {
            throw ValidationException::withMessages([
                'categories' => 'Os percentuais de cada entrada devem somar 100%.',
            ]);
        }

        DB::transaction(function () use ($submitted): void {
            foreach ($submitted as $id => $values) {
                Category::query()->whereKey($id)->update([
                    'normal_percentage' => $values['normal_percentage'],
                    'extra_percentage' => $values['extra_percentage'],
                ]);
            }
        });

        return $this->index();
    }

    private function serialize(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'normal_percentage' => $category->normal_percentage,
            'extra_percentage' => $category->extra_percentage,
            'active' => $category->active,
        ];
    }
}
