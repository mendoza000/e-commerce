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

        // Snapshot of the real USDT/VES P2P rate (CriptoYa, see PRD 8bis) at the
        // time this seeder was last updated — not live-updating. A believable
        // starting point for demo purposes; Fase 4's CriptoYaRateProvider is
        // what keeps this current in a real deployment.
        $rates = [
            ['from_currency_id' => $usd->id, 'to_currency_id' => $ves->id, 'rate' => 737],
            ['from_currency_id' => $usdt->id, 'to_currency_id' => $ves->id, 'rate' => 737],
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
