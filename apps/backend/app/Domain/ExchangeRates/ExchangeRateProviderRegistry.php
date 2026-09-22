<?php

namespace App\Domain\ExchangeRates;

use App\Domain\Enums\ExchangeRateProviderType;
use App\Domain\ExchangeRates\Contracts\ExchangeRateProviderInterface;
use App\Domain\ExchangeRates\Providers\ManualRateProvider;

/**
 * Turns the `provider` column of an exchange_rate_settings row into its
 * provider object. Use ExchangeRateSetting::provider() at call sites.
 */
class ExchangeRateProviderRegistry
{
    public function for(?string $provider): ExchangeRateProviderInterface
    {
        $type = $provider === null ? null : ExchangeRateProviderType::tryFrom($provider);

        // An unset or unrecognised provider is treated as manual: the worst
        // case is that the admin keeps entering the rate by hand, never that a
        // pair silently gets rates from an unexpected source.
        if ($type === null) {
            return new ManualRateProvider;
        }

        $class = $type->providerClass();

        return new $class;
    }
}
