<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_movements', function (Blueprint $table) {
            $table->foreignId('purchase_goal_id')
                ->nullable()
                ->after('balance_account_id')
                ->constrained('purchase_goals')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('balance_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_goal_id');
        });
    }
};