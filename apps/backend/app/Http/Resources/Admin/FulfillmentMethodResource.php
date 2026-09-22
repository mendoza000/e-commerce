<?php

namespace App\Http\Resources\Admin;

use App\Models\FulfillmentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shipping method as the configuration screen needs it. Mirrors
 * Admin\PaymentMethodResource; zone_rates travels separately (loaded only on
 * the detail view) since the list view has no use for it.
 *
 * @mixin FulfillmentMethod
 */
class FulfillmentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'label' => $this->label,
            'requires_tracking_code' => $this->requires_tracking_code,
            'base_cost' => $this->base_cost,
            'currency' => $this->whenLoaded('currency', fn () => $this->currency ? [
                'id' => $this->currency->id,
                'code' => $this->currency->code,
                'symbol' => $this->currency->symbol,
                'decimal_places' => $this->currency->decimal_places,
            ] : null),
            'is_active' => $this->is_active,
            'position' => $this->position,
            'zone_rates' => FulfillmentZoneRateResource::collection($this->whenLoaded('zoneRates')),
            // How many orders picked this method — the reason it can be
            // deactivated but not deleted once it has been used.
            'orders_count' => $this->whenCounted('orders'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
