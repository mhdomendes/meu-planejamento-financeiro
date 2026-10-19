<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BalanceAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            'Saldo Essenciais',
            'Saldo Estilo de Vida',
        ];

        foreach ($accounts as $name) {
            DB::table('balance_accounts')->updateOrInsert(
                ['name' => $name],
                [
                    'initial_balance' => 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}


