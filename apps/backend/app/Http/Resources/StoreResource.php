<?php

namespace App\Http\Resources;

use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the storefront may know about the store it is showing: its name, its
 * logo, its colours, the WhatsApp number customers write to, and the currency
 * prices are expressed in.
 *
 * Deliberately narrower than the admin resource — no ids of enabled
 * currencies, no rate health, no timestamps. This is a public endpoint, and
 * everything on it is something already printed on the page.
 *
 * @mixin StoreSetting
 */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'store_name' => $this->store_name,
            'logo_url' => $this->logoUrl(),
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'whatsapp_number' => $this->whatsapp_number,
            'base_currency' => $this->whenLoaded('baseCurrency', fn () => [
                'code' => $this->baseCurrency->code,
                'symbol' => $this->baseCurrency->symbol,
                'decimal_places' => $this->baseCurrency->decimal_places,
            ]),
        ];
    }
}
