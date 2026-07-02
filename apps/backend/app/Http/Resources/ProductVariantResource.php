<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price_override' => $this->price_override,
            'effective_price' => $this->effectivePrice(),
            'stock' => $this->stock,
            'reserved_quantity' => $this->reserved_quantity,
            'available_stock' => max(0, $this->stock - $this->reserved_quantity),
            'is_active' => $this->is_active,
            'option_value_ids' => $this->whenLoaded(
                'optionValues',
                fn () => $this->optionValues->pluck('id')->values()
            ),
        ];
    }
}
