<?php

namespace App\Http\Resources\Admin;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method as the configuration screen needs it.
 *
 * `instruction_fields` is what lets the panel draw the right form without
 * hardcoding a field list per type: the same list the provider reads the blob
 * with, and the same one the request validates against.
 *
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'label' => $this->label,
            'is_active' => $this->is_active,
            'position' => $this->position,
            'requires_proof' => $this->requiresProof(),
            'currency' => $this->whenLoaded('currency', fn () => [
                'id' => $this->currency->id,
                'code' => $this->currency->code,
                'symbol' => $this->currency->symbol,
                'decimal_places' => $this->currency->decimal_places,
            ]),
            'instructions' => $this->instructions,
            'instruction_fields' => $this->type->instructionFields(),
            // How many orders were paid this way — the reason a method can be
            // deactivated but not deleted once it has been used.
            'orders_count' => $this->whenCounted('orders'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
