<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Despesas essenciais',
                'normal_percentage' => 45,
                'extra_percentage' => 45,
                'active' => true,
            ],
            [
                'name' => 'Estilo de vida',
                'normal_percentage' => 25,
                'extra_percentage' => 25,
                'active' => true,
            ],
            [
                'name' => 'Investimentos',
                'normal_percentage' => 30,
                'extra_percentage' => 30,
                'active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::query()->firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
