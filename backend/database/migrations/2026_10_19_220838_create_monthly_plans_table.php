<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_plans', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->decimal('normal_income', 12, 2)->default(0);
            $table->decimal('extra_income', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_plans');
    }
};