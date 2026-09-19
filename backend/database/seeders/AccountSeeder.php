<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        Account::create([
            'numero_cuenta' => '0001-0001',
            'saldo_cache' => 1000.00,
        ]);

        Account::create([
            'numero_cuenta' => '0001-0002',
            'saldo_cache' => 500.00,
        ]);
    }
}