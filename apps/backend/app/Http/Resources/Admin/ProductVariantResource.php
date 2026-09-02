<?php

namespace App\Http\Resources\Admin;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A variant with the numbers the panel has to keep apart: `stock` is what is
 * on the shelf, `reserved_quantity` is what open orders have already claimed,
 * and `available_stock` is the only one a new order can draw from.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price_override' => $this->price_override,
            'effective_price' => $this->when(
                $this->relationLoaded('product') || $this->price_override !== null,
                fn () => $this->effectivePrice(),
            ),
            'stock' => $this->stock,
            'reserved_quantity' => $this->reserved_quantity,
            'available_stock' => $this->availableStock(),
            'is_active' => $this->is_active,
            'is_archived' => $this->trashed(),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ]),
            // The combination that defines this variant. The option name comes
            // along so the panel can print "Color: Rojo" without a second
            // lookup.
            'option_values' => $this->whenLoaded('optionValues', fn () => $this->optionValues
                ->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'product_option_id' => $value->product_option_id,
                    'option_name' => $value->relationLoaded('option') ? $value->option?->name : null,
                ])
                ->values()),
        ];
    }
}
