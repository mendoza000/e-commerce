<?php

namespace App\Domain\ExchangeRates\Contracts;

use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Models\Currency;

/**
 * One implementation per rate source (see PRD section 8bis).
 *
 * A provider only fetches a number. Persisting it, honouring the configured
 * frequency and recording failures is the ExchangeRateSetting model's and the
 * ExchangeRateService's job.
 */
interface ExchangeRateProviderInterface
{
    /**
     * The rate as a decimal string (never a float — money precision, see
     * ProductVariant::effectivePrice).
     *
     * @param  string|null  $referenceAmount  Volume the quote is asked for, when the source is volume-sensitive (P2P order books are).
     *
     * @throws ExchangeRateUnavailable
     */
    public function getRate(Currency $from, Currency $to, ?string $referenceAmount = null): string;

    /**
     * Stored verbatim in `exchange_rates.source`, so any historical rate can be
     * traced back to where it came from.
     */
    public function getSourceName(): string;

    /**
     * Whether the scheduled refresh should call this provider at all. Manual
     * rates are typed in by the admin and must never be overwritten by a job.
     */
    public function isAutomatic(): bool;
}
