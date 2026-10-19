<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();

            $table->string('description');

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            $table->unsignedSmallInteger('repeat_every_months')->default(1);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['active', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};