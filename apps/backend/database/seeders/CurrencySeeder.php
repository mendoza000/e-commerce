<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'VES', 'name' => 'Bolívar', 'symbol' => 'Bs.', 'decimal_places' => 0],
            ['code' => 'USD', 'name' => 'Dólar', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'USDT', 'name' => 'Tether', 'symbol' => 'USDT', 'decimal_places' => 2],
            ['code' => 'COP', 'name' => 'Peso Colombiano', 'symbol' => 'COP$', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [...$currency, 'is_active' => true]
            );
        }
    }
}
