<?php

namespace App\Http\Resources;

use App\Models\FulfillmentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FulfillmentMethod
 */
class FulfillmentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->label,
            'requires_tracking_code' => $this->requires_tracking_code,
            'currency' => $this->whenLoaded('currency', fn () => $this->currency ? [
                'id' => $this->currency->id,
                'code' => $this->currency->code,
                'symbol' => $this->currency->symbol,
                'decimal_places' => $this->currency->decimal_places,
            ] : null),
            // Set by Api\FulfillmentMethodController when the request carried a
            // state to price against. Null either way: no destination was
            // given yet, or the store has not priced this one ("a coordinar")
            // — the frontend cannot show a number either way.
            'estimated_cost' => $this->estimated_cost,
        ];
    }
}
