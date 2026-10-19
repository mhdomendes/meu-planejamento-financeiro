<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_goals', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->foreignId('balance_account_id')
                ->constrained('balance_accounts')
                ->restrictOnDelete();

            $table->decimal('target_amount', 12, 2);
            $table->string('image_url', 2048)->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_goals');
    }
};