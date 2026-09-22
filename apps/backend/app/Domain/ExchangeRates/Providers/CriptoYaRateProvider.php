<?php

namespace App\Domain\ExchangeRates\Providers;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProviderInterface;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads the P2P order book published by CriptoYa (PRD section 8bis):
 * {base_url}/{exchange}/{coin}/{fiat}/{volume}
 *
 * The quote is volume-sensitive, which is why each currency pair carries its
 * own `reference_amount` in exchange_rate_settings.
 *
 * This class is only ever reached from the scheduled refresh command. Checkout
 * reads the last stored rate from the database and never makes this call.
 */
class CriptoYaRateProvider implements ExchangeRateProviderInterface
{
    private const DEFAULT_VOLUME = '1';

    /**
     * Preferred response keys, in order. `totalAsk` includes the exchange's
     * fees, so it is the closest thing to what the customer really pays.
     */
    private const RATE_KEYS = ['totalAsk', 'ask'];

    public function getRate(Currency $from, Currency $to, ?string $referenceAmount = null): string
    {
        $url = sprintf(
            '%s/%s/%s/%s/%s',
            rtrim((string) config('services.criptoya.base_url'), '/'),
            config('services.criptoya.exchange'),
            strtolower($from->code),
            strtolower($to->code),
            $this->normaliseVolume($referenceAmount),
        );

        try {
            $response = Http::timeout((int) config('services.criptoya.timeout'))
                ->retry((int) config('services.criptoya.retries'), 200, throw: false)
                ->acceptJson()
                ->get($url);
        } catch (Throwable $e) {
            throw new ExchangeRateUnavailable("No se pudo contactar a CriptoYa: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new ExchangeRateUnavailable("CriptoYa respondió {$response->status()} para {$from->code}/{$to->code}.");
        }

        return $this->extractRate($response->json() ?? [], $from, $to);
    }

    public function getSourceName(): string
    {
        return 'criptoya:'.config('services.criptoya.exchange');
    }

    public function isAutomatic(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractRate(array $payload, Currency $from, Currency $to): string
    {
        foreach (self::RATE_KEYS as $key) {
            $value = $payload[$key] ?? null;

            if (is_numeric($value) && (float) $value > 0) {
                return (string) $value;
            }
        }

        throw new ExchangeRateUnavailable(
            "La respuesta de CriptoYa para {$from->code}/{$to->code} no contiene una tasa utilizable."
        );
    }

    private function normaliseVolume(?string $referenceAmount): string
    {
        if ($referenceAmount === null || ! is_numeric($referenceAmount) || (float) $referenceAmount <= 0) {
            return self::DEFAULT_VOLUME;
        }

        // The endpoint takes a plain number: trim the decimal padding the
        // decimal:6 cast adds ("100.000000" => "100").
        return rtrim(rtrim($referenceAmount, '0'), '.') ?: self::DEFAULT_VOLUME;
    }
}
