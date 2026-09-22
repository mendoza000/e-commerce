<?php

namespace App\Http\Resources\Admin;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the kardex.
 *
 * `created_by` is null for everything the system did on its own — a customer's
 * reservation, the scheduled sweep releasing an expired one — and carries a
 * name only when a person decided it. The panel prints "Sistema" for the
 * former, which is why the null is passed through rather than hidden.
 *
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            // Signed: negative takes units off the shelf, positive puts them
            // back. Reading the sign is how the kardex is audited.
            'quantity_change' => $this->quantity_change,
            'reason' => $this->reason,
            // Kept on the row itself so the line still says what moved even
            // after the variant is gone.
            'sku' => $this->sku,
            'product_variant_id' => $this->product_variant_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
