<?php

namespace Database\Seeders;

use App\Domain\Enums\ExchangeRateMode;
use App\Models\Currency;
use App\Models\ExchangeRateSetting;
use Illuminate\Database\Seeder;

class ExchangeRateSettingSeeder extends Seeder
{
    public function run(): void
    {
        $ves = Currency::where('code', 'VES')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $usdt = Currency::where('code', 'USDT')->firstOrFail();

        foreach ([$usd, $usdt] as $fromCurrency) {
            ExchangeRateSetting::updateOrCreate(
                [
                    'from_currency_id' => $fromCurrency->id,
                    'to_currency_id' => $ves->id,
                ],
                [
                    'mode' => ExchangeRateMode::Manual,
                    'is_active' => true,
                ]
            );
        }
    }
}
