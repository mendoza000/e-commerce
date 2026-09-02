<?php

namespace App\Http\Resources\Admin;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plain currency row, for the pickers the settings screens are made of.
 *
 * Deliberately not the storefront's CurrencyResource: that one wraps a
 * currency together with its rate and a base flag, which are answers about a
 * particular store, not about the currency itself.
 *
 * @mixin Currency
 */
class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimal_places' => $this->decimal_places,
            'is_active' => $this->is_active,
        ];
    }
}
