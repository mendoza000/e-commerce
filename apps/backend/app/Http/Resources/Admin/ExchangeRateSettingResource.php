<?php

namespace App\Http\Resources\Admin;

use App\Models\ExchangeRateSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How one currency pair is kept up to date, and whether that is working.
 *
 * The health block is the reason this resource is worth having: a failed
 * automatic refresh writes nothing to `exchange_rates`, so the store keeps
 * quoting the last good rate and a broken source stays invisible until the
 * number is badly stale (PRD 8bis).
 *
 * @mixin ExchangeRateSetting
 */
class ExchangeRateSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pair' => $this->whenLoaded('fromCurrency', fn () => $this->pairLabel()),
            'from_currency' => $this->whenLoaded('fromCurrency', fn () => [
                'id' => $this->fromCurrency->id,
                'code' => $this->fromCurrency->code,
            ]),
            'to_currency' => $this->whenLoaded('toCurrency', fn () => [
                'id' => $this->toCurrency->id,
                'code' => $this->toCurrency->code,
            ]),
            'mode' => $this->mode->value,
            'provider' => $this->provider,
            'frequency_minutes' => $this->frequency_minutes,
            'reference_amount' => $this->reference_amount,
            'is_active' => $this->is_active,
            'health' => [
                'status' => $this->healthStatus(),
                'last_run_at' => $this->last_run_at?->toISOString(),
                'last_error_at' => $this->last_error_at?->toISOString(),
                'last_error' => $this->last_error,
                // Whether the next scheduler tick would pick this pair up. A
                // pair that is never due is a frequency the admin set too high.
                'is_due' => $this->isDueForRefresh(),
            ],
            // The newest rate stored for this pair, so the listing can show
            // what the store is actually quoting right now.
            'latest_rate' => $this->when(
                $this->latest_rate !== null,
                fn () => ExchangeRateResource::make($this->latest_rate),
            ),
        ];
    }
}
