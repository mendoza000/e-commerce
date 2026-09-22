<?php

namespace App\Http\Resources\Admin;

use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the rate history.
 *
 * `created_by` is null for every rate a provider produced and carries a name
 * only for one an admin typed in — the same convention as the kardex. That is
 * the whole point of keeping the history: being able to say where a number
 * that priced an order came from.
 *
 * @mixin ExchangeRate
 */
class ExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rate' => $this->rate,
            'source' => $this->source,
            'reference_amount' => $this->reference_amount,
            'effective_at' => $this->effective_at?->toISOString(),
            'from_currency' => $this->whenLoaded('fromCurrency', fn () => [
                'id' => $this->fromCurrency->id,
                'code' => $this->fromCurrency->code,
            ]),
            'to_currency' => $this->whenLoaded('toCurrency', fn () => [
                'id' => $this->toCurrency->id,
                'code' => $this->toCurrency->code,
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
        ];
    }
}
