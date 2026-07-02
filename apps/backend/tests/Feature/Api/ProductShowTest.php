<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductOptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_full_detail_with_options_variants_and_stock(): void
    {
        $product = Product::factory()
            ->withVariants([
                'Color' => ['Rojo', 'Azul'],
                'Talla' => ['S', 'M'],
            ])
            ->create(['name' => 'Camisa', 'slug' => 'camisa', 'base_price' => 20.0]);

        $variant = $product->variants()->first();
        $variant->update(['stock' => 10, 'reserved_quantity' => 3, 'price_override' => null]);

        $response = $this->getJson("/api/products/{$product->slug}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $product->id,
                'name' => 'Camisa',
                'slug' => 'camisa',
            ],
        ]);
        $response->assertJsonCount(2, 'data.options');
        $response->assertJsonCount(4, 'data.variants');

        $variantPayload = collect($response->json('data.variants'))
            ->firstWhere('id', $variant->id);

        $this->assertSame(7, $variantPayload['available_stock']);
        $this->assertEquals(20.0, (float) $variantPayload['effective_price']);
        $this->assertCount(2, $variantPayload['option_value_ids']);
    }

    public function test_available_stock_never_goes_negative(): void
    {
        $product = Product::factory()->withVariants([])->create();
        $variant = $product->variants()->first();
        $variant->update(['stock' => 2, 'reserved_quantity' => 5]);

        $response = $this->getJson("/api/products/{$product->slug}");

        $response->assertOk();
        $this->assertSame(0, $response->json('data.variants.0.available_stock'));
    }

    public function test_variant_uses_price_override_when_present(): void
    {
        $product = Product::factory()->withVariants([])->create(['base_price' => 20.0]);
        $variant = $product->variants()->first();
        $variant->update(['price_override' => 15.5]);

        $response = $this->getJson("/api/products/{$product->slug}");

        $this->assertEquals(15.5, (float) $response->json('data.variants.0.effective_price'));
    }

    public function test_product_without_options_still_returns_implicit_variant(): void
    {
        $product = Product::factory()->withVariants([])->create();

        $response = $this->getJson("/api/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonCount(0, 'data.options');
        $response->assertJsonCount(1, 'data.variants');
    }

    public function test_product_level_fallback_images_are_always_present(): void
    {
        $product = Product::factory()->withVariants([
            'Color' => ['Rojo'],
        ])->create();

        $product->images()->create([
            'product_option_value_id' => null,
            'path' => 'products/fallback.jpg',
            'position' => 0,
            'is_primary' => true,
        ]);

        $redValue = ProductOptionValue::where('value', 'Rojo')->first();
        $product->images()->create([
            'product_option_value_id' => $redValue->id,
            'path' => 'products/red.jpg',
            'position' => 0,
            'is_primary' => false,
        ]);

        $response = $this->getJson("/api/products/{$product->slug}");

        $response->assertOk();
        $images = collect($response->json('data.images'));

        $this->assertTrue($images->contains(fn ($image) => $image['product_option_value_id'] === null));
        $this->assertTrue($images->contains(fn ($image) => $image['product_option_value_id'] === $redValue->id));
    }

    public function test_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/products/does-not-exist');

        $response->assertStatus(404);
        $response->assertJson(['error' => ['code' => 'not_found']]);
    }

    public function test_returns_404_for_inactive_product(): void
    {
        $product = Product::factory()->withVariants([])->create(['is_active' => false]);

        $response = $this->getJson("/api/products/{$product->slug}");

        $response->assertStatus(404);
    }
}
