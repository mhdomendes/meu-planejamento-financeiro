<?php

namespace Database\Seeders;

use App\Models\BalanceAccount;
use Illuminate\Database\Seeder;

class BalanceAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            'Saldo Essenciais',
            'Saldo Estilo de Vida',
        ];

        foreach ($accounts as $name) {
            BalanceAccount::query()->firstOrCreate(
                ['name' => $name],
                ['initial_balance' => 0]
            );
        }
    }
}
