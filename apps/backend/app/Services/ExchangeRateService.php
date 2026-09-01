<?php

namespace App\Services;

use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateSetting;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Log;

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

    /**
     * Fetches one configured pair and stores the result as a new historical
     * row. Called only by the scheduled exchange-rates:refresh command.
     *
     * A failure is swallowed on purpose (PRD 8bis): nothing is written, the
     * incident is recorded on the setting and logged for the admin, and the
     * last valid rate stays in force so checkout keeps working.
     *
     * @return ExchangeRate|null The rate just stored, or null if the fetch failed.
     */
    public function refresh(ExchangeRateSetting $setting): ?ExchangeRate
    {
        $setting->loadMissing(['fromCurrency', 'toCurrency']);

        $provider = $setting->rateProvider();

        if (! $provider->isAutomatic()) {
            return null;
        }

        try {
            $rate = $provider->getRate(
                $setting->fromCurrency,
                $setting->toCurrency,
                $setting->reference_amount === null ? null : (string) $setting->reference_amount,
            );
        } catch (ExchangeRateUnavailable $e) {
            $setting->markFailed($e->getMessage());

            Log::warning('No se pudo actualizar la tasa de cambio.', [
                'pair' => $setting->pairLabel(),
                'provider' => $setting->provider,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }

        $stored = ExchangeRate::create([
            'from_currency_id' => $setting->from_currency_id,
            'to_currency_id' => $setting->to_currency_id,
            'rate' => $rate,
            'source' => $provider->getSourceName(),
            'reference_amount' => $setting->reference_amount,
            'effective_at' => now(),
            'created_by' => null,
        ]);

        $setting->markRefreshed();

        return $stored;
    }
}
