<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The product as the catalogue screen needs it: the counts that tell an admin
 * whether a product is finished (does it have variants? images?), and the two
 * pieces of state the storefront resource has no reason to know about —
 * whether it is published, and whether it is archived.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'is_active' => $this->is_active,
            // Archived, not deleted: the row is still there because the order
            // history points at it. See Product::archive().
            'is_archived' => $this->trashed(),
            'archived_at' => $this->deleted_at?->toISOString(),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'variants_count' => $this->whenCounted('variants'),
            'options_count' => $this->whenCounted('options'),
            'images_count' => $this->whenCounted('images'),
            // Sum across variants, because stock lives on the variant and
            // never on the product. Null until the query asks for it.
            'total_stock' => $this->when(
                $this->variants_sum_stock !== null,
                fn () => (int) $this->variants_sum_stock,
            ),
            'primary_image' => $this->whenLoaded(
                'images',
                fn () => $this->images->isNotEmpty() ? ProductImageResource::make($this->images->first()) : null,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
