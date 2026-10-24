<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_goals', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(1);
            $table->index(['balance_account_id', 'priority']);
        });

        DB::table('balance_accounts')
            ->orderBy('id')
            ->each(function (object $account): void {
                DB::table('purchase_goals')
                    ->where('balance_account_id', $account->id)
                    ->orderBy('id')
                    ->get(['id'])
                    ->each(function (object $goal, int $index): void {
                        DB::table('purchase_goals')
                            ->where('id', $goal->id)
                            ->update(['priority' => $index + 1]);
                    });
            });
    }

    public function down(): void
    {
        Schema::table('purchase_goals', function (Blueprint $table) {
            $table->dropIndex(['balance_account_id', 'priority']);
            $table->dropColumn('priority');
        });
    }
};
