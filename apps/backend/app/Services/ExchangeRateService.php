<?php

namespace App\Services;

use App\Domain\Enums\ExchangeRateProviderType;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateSetting;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
     * The admin types a rate in by hand.
     *
     * Always a new row, never an update of the last one: `exchange_rates` is
     * the history an order's frozen rate is justified against, and rewriting a
     * past row would make an order that was correct at the time look wrong.
     * "Correcting" a rate means entering the right one now, and the newest
     * `effective_at` is what latestRate() picks up.
     *
     * @param  string  $rate  Decimal string, never a float — money precision.
     *
     * @throws ValidationException
     */
    public function storeManual(
        Currency $from,
        Currency $to,
        string $rate,
        ?string $referenceAmount = null,
        ?User $admin = null,
    ): ExchangeRate {
        if ($from->is($to)) {
            throw ValidationException::withMessages([
                'to_currency_id' => ['Una moneda no se cambia por sí misma.'],
            ]);
        }

        return ExchangeRate::create([
            'from_currency_id' => $from->id,
            'to_currency_id' => $to->id,
            'rate' => $rate,
            // Verbatim in the column, so a historical rate can always be traced
            // back to whether a person or a source produced it.
            'source' => ExchangeRateProviderType::Manual->value,
            'reference_amount' => $referenceAmount,
            'effective_at' => now(),
            // The mirror image of refresh(), which leaves this null on purpose:
            // there, nobody decided the number, a provider reported it.
            'created_by' => $admin?->id,
        ]);
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
