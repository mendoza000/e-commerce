<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => Str::upper(Str::random(8)),
            'price_override' => null,
            'stock' => fake()->numberBetween(0, 100),
            'reserved_quantity' => 0,
            'reserved_until' => null,
            'is_active' => true,
        ];
    }
}
