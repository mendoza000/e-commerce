<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        $ves = Currency::where('code', 'VES')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $usdt = Currency::where('code', 'USDT')->firstOrFail();

        $rates = [
            ['from_currency_id' => $usd->id, 'to_currency_id' => $ves->id, 'rate' => 40],
            ['from_currency_id' => $usdt->id, 'to_currency_id' => $ves->id, 'rate' => 40],
        ];

        foreach ($rates as $rate) {
            ExchangeRate::create([
                ...$rate,
                'source' => 'manual',
                'effective_at' => now(),
            ]);
        }
    }
}
