<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20);
            $table->string('type', 20);
            $table->string('logo_path')->nullable();
            $table->decimal('quantity', 20, 8);
            $table->decimal('quote_value', 18, 4);
            $table->decimal('ideal_percentage', 5, 2);
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
