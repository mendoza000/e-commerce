<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_image_has_no_option_value(): void
    {
        $product = Product::factory()
            ->withVariants(['Color' => ['Rojo', 'Azul']])
            ->create();

        $generalImage = ProductImage::create([
            'product_id' => $product->id,
            'product_option_value_id' => null,
            'path' => 'images/general.jpg',
            'position' => 0,
            'is_primary' => true,
        ]);

        $this->assertNull($generalImage->product_option_value_id);
        $this->assertNull($generalImage->optionValue);
    }

    public function test_option_specific_image_resolves_option_value_and_is_shared_across_matching_variants(): void
    {
        $product = Product::factory()
            ->withVariants(['Color' => ['Rojo', 'Azul']])
            ->create();

        $rojoValue = $product->options()
            ->where('name', 'Color')
            ->first()
            ->values()
            ->where('value', 'Rojo')
            ->firstOrFail();

        $rojoImage = ProductImage::create([
            'product_id' => $product->id,
            'product_option_value_id' => $rojoValue->id,
            'path' => 'images/rojo.jpg',
            'position' => 1,
            'is_primary' => false,
        ]);

        $this->assertSame('Rojo', $rojoImage->optionValue->value);

        // Every variant carrying the "Rojo" option value must be able to
        // reach the shared image by traversing optionValues -> images.
        $variantsWithRojo = $product->variants()
            ->whereHas('optionValues', fn ($query) => $query->where('product_option_values.id', $rojoValue->id))
            ->get();

        $this->assertGreaterThan(0, $variantsWithRojo->count());

        foreach ($variantsWithRojo as $variant) {
            $reachableImages = $variant->optionValues->flatMap(fn ($optionValue) => $optionValue->images);

            $this->assertTrue(
                $reachableImages->contains(fn (ProductImage $image) => $image->is($rojoImage)),
                "Variant {$variant->id} could not reach the shared 'Rojo' image."
            );
        }
    }
}
