<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Every write to a product's catalogue answers with the product as the editor
 * needs to redraw it, so the panel replaces what it is showing instead of
 * fetching it again. The eager-load list lives here because "what a product
 * detail includes" has to be one answer, not one per controller.
 */
trait LoadsProductDetail
{
    protected function productDetail(Product $product): Product
    {
        $product->load([
            'category',
            'options' => fn ($query) => $query->orderBy('position')->orderBy('id'),
            // The count is what tells the panel which values are safe to
            // delete; it is a count rather than the rows because a value can
            // back dozens of variants and none of them are needed here.
            'options.values' => fn ($query) => $query->withCount('variants')->orderBy('position')->orderBy('id'),
            'variants' => fn ($query) => $query->orderBy('sku'),
            'variants.optionValues.option',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('position')->orderBy('id'),
        ]);

        // The variants already have their product in hand — it is the one being
        // returned — so effectivePrice() must not go and fetch it once per row.
        $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        return $product;
    }
}
