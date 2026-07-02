<?php

namespace App\Http\Resources;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{currency: Currency, is_base: bool, rate: ?ExchangeRate} $resource
 */
class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->resource['currency'];
        $isBase = $this->resource['is_base'];
        $rate = $this->resource['rate'];

        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => $currency->decimal_places,
            'is_base' => $isBase,
            'rate' => $isBase ? '1.000000' : $rate?->rate,
            'rate_effective_at' => $rate?->effective_at?->toISOString(),
        ];
    }
}
