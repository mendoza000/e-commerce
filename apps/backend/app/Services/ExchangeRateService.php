<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\StoreSetting;

class ExchangeRateService
{
    public function latestRate(Currency $from, Currency $to): ?ExchangeRate
    {
        if ($from->is($to)) {
            return null;
        }

        return ExchangeRate::query()
            ->where('from_currency_id', $from->id)
            ->where('to_currency_id', $to->id)
            ->orderByDesc('effective_at')
            ->first();
    }

    /**
     * @return array<int, array{currency: Currency, is_base: bool, rate: ?ExchangeRate}>
     */
    public function enabledCurrenciesWithRates(StoreSetting $store): array
    {
        $base = $store->baseCurrency;

        return $store->enabledCurrencies->map(fn (Currency $currency) => [
            'currency' => $currency,
            'is_base' => $currency->is($base),
            'rate' => $currency->is($base) ? null : $this->latestRate($base, $currency),
        ])->all();
    }
}
