<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_income_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('normal_income', 12, 2)->default(0);
            $table->timestamps();
        });

        $normalIncome = DB::table('monthly_plans')
            ->orderByDesc('month')
            ->value('normal_income') ?? 0;

        DB::table('recurring_income_settings')->insert([
            'id' => 1,
            'normal_income' => $normalIncome,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_income_settings');
    }
};
