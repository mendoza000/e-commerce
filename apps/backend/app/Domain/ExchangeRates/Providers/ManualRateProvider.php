<?php

namespace App\Domain\ExchangeRates\Providers;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProviderInterface;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Models\Currency;

/**
 * The admin types the rate in by hand. There is nothing to fetch, so this
 * provider exists purely so that "manual" is a first-class mode rather than a
 * null check scattered through the refresh logic.
 */
class ManualRateProvider implements ExchangeRateProviderInterface
{
    public function getRate(Currency $from, Currency $to, ?string $referenceAmount = null): string
    {
        throw new ExchangeRateUnavailable(
            'Las tasas manuales las define el administrador; no se obtienen automáticamente.'
        );
    }

    public function getSourceName(): string
    {
        return 'manual';
    }

    public function isAutomatic(): bool
    {
        return false;
    }
}
