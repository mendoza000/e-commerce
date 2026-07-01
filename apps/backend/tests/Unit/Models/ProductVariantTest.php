<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_resolves_product_and_option_values(): void
    {
        $product = Product::factory()
            ->withVariants([
                'Color' => ['Rojo', 'Azul'],
                'Talla' => ['S', 'M'],
            ])
            ->create();

        $variant = $product->variants()->first();

        $this->assertNotNull($variant);
        $this->assertTrue($variant->product->is($product));

        $optionValues = $variant->optionValues;

        $this->assertCount(2, $optionValues);
        $this->assertEqualsCanonicalizing(
            ['Color', 'Talla'],
            $optionValues->map(fn ($value) => $value->option->name)->all()
        );
    }

    public function test_effective_price_falls_back_to_product_base_price_when_no_override(): void
    {
        $product = Product::factory()->create(['base_price' => 49.990000]);
        $variant = $product->variants()->create([
            'sku' => 'SKU-NO-OVERRIDE',
            'price_override' => null,
            'stock' => 10,
            'reserved_quantity' => 0,
            'reserved_until' => null,
            'is_active' => true,
        ]);

        $this->assertEqualsWithDelta(
            (float) $product->base_price,
            (float) $variant->effectivePrice(),
            0.000001
        );
    }

    public function test_effective_price_uses_override_when_present(): void
    {
        $product = Product::factory()->create(['base_price' => 49.990000]);
        $variant = $product->variants()->create([
            'sku' => 'SKU-OVERRIDE',
            'price_override' => 39.990000,
            'stock' => 10,
            'reserved_quantity' => 0,
            'reserved_until' => null,
            'is_active' => true,
        ]);

        $this->assertEqualsWithDelta(
            39.990000,
            (float) $variant->effectivePrice(),
            0.000001
        );
    }
}
