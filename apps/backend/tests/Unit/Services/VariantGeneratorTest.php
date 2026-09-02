<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\VariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariantGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private VariantGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new VariantGenerator;
    }

    // -----------------------------------------------------------------
    // The implicit variant (Fase 1 rule)
    // -----------------------------------------------------------------

    public function test_a_product_without_options_gets_a_single_implicit_variant(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa-lisa']);

        $result = $this->generator->generate($product);

        $this->assertCount(1, $result['created']);
        $this->assertSame(0, $result['skipped']);

        $variant = $product->variants()->sole();

        $this->assertSame('CAMISA-LISA', $variant->sku);
        $this->assertTrue($variant->optionValues()->doesntExist());
    }

    public function test_generating_again_on_a_product_without_options_does_not_duplicate_it(): void
    {
        $product = Product::factory()->create();

        $this->generator->generate($product);
        $result = $this->generator->generate($product);

        $this->assertCount(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_an_explicit_combination_on_a_product_without_options_is_rejected(): void
    {
        $product = Product::factory()->create();
        $foreign = $this->option(Product::factory()->create(), 'Color', ['Rojo']);

        $this->expectException(ValidationException::class);

        $this->generator->generate($product, [[$foreign->values->first()->id]]);
    }

    // -----------------------------------------------------------------
    // Generating the grid
    // -----------------------------------------------------------------

    public function test_it_generates_every_combination_of_the_option_grid(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $this->option($product, 'Color', ['Rojo', 'Azul']);
        $this->option($product, 'Talla', ['M', 'L']);

        $result = $this->generator->generate($product);

        $this->assertCount(4, $result['created']);
        $this->assertSame(4, $product->variants()->count());

        $this->assertEqualsCanonicalizing(
            ['CAMISA-ROJO-M', 'CAMISA-ROJO-L', 'CAMISA-AZUL-M', 'CAMISA-AZUL-L'],
            $product->variants()->pluck('sku')->all(),
        );
    }

    public function test_it_generates_only_the_combinations_it_was_given(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $color = $this->option($product, 'Color', ['Rojo', 'Azul']);
        $talla = $this->option($product, 'Talla', ['M', 'L']);

        $result = $this->generator->generate($product, [
            [$color->values->firstWhere('value', 'Rojo')->id, $talla->values->firstWhere('value', 'M')->id],
        ]);

        $this->assertCount(1, $result['created']);
        $this->assertSame(['CAMISA-ROJO-M'], $product->variants()->pluck('sku')->all());
    }

    public function test_running_the_same_generation_twice_creates_nothing_the_second_time(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $this->option($product, 'Color', ['Rojo', 'Azul']);
        $this->option($product, 'Talla', ['M', 'L']);

        $this->generator->generate($product);
        $result = $this->generator->generate($product->fresh());

        $this->assertCount(0, $result['created']);
        $this->assertSame(4, $result['skipped']);
        $this->assertSame(4, $product->variants()->count());
    }

    public function test_a_second_run_adds_only_the_combinations_a_new_value_created(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $color = $this->option($product, 'Color', ['Rojo']);

        $this->generator->generate($product);

        ProductOptionValue::factory()->create([
            'product_option_id' => $color->id,
            'value' => 'Azul',
            'position' => 1,
        ]);

        $result = $this->generator->generate($product->fresh());

        $this->assertCount(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(2, $product->variants()->count());
    }

    public function test_the_order_of_the_ids_sent_does_not_change_the_sku(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $color = $this->option($product, 'Color', ['Rojo']);
        $talla = $this->option($product, 'Talla', ['M']);

        // Talla first, Color second — the reverse of the option positions.
        $this->generator->generate($product, [
            [$talla->values->first()->id, $color->values->first()->id],
        ]);

        $this->assertSame('CAMISA-ROJO-M', $product->variants()->sole()->sku);
    }

    // -----------------------------------------------------------------
    // Replacing the implicit variant
    // -----------------------------------------------------------------

    public function test_generating_real_combinations_archives_the_implicit_variant(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $this->generator->generate($product);

        $implicit = $product->variants()->sole();

        $this->option($product, 'Color', ['Rojo']);

        $result = $this->generator->generate($product->fresh());

        $this->assertSame(1, $result['archived_implicit']);
        $this->assertSoftDeleted('product_variants', ['id' => $implicit->id]);
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame('CAMISA-ROJO', $product->variants()->sole()->sku);
    }

    public function test_it_refuses_to_archive_an_implicit_variant_holding_units_for_an_open_order(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $this->generator->generate($product);

        $product->variants()->sole()->update(['stock' => 10, 'reserved_quantity' => 2]);

        $this->option($product, 'Color', ['Rojo']);

        try {
            $this->generator->generate($product->fresh());
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('combinations', $e->errors());
        }

        // Nothing was written: the refusal happens before any variant is made.
        $this->assertSame(1, $product->variants()->count());
    }

    // -----------------------------------------------------------------
    // Rejections
    // -----------------------------------------------------------------

    public function test_a_combination_that_misses_an_option_is_rejected(): void
    {
        $product = Product::factory()->create();
        $color = $this->option($product, 'Color', ['Rojo']);
        $this->option($product, 'Talla', ['M']);

        try {
            $this->generator->generate($product, [[$color->values->first()->id]]);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('combinations.0', $e->errors());
        }

        $this->assertSame(0, $product->variants()->count());
    }

    public function test_a_value_belonging_to_another_product_is_rejected(): void
    {
        $product = Product::factory()->create();
        $this->option($product, 'Color', ['Rojo']);

        $other = $this->option(Product::factory()->create(), 'Color', ['Verde']);

        try {
            $this->generator->generate($product, [[$other->values->first()->id]]);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('combinations.0', $e->errors());
        }
    }

    public function test_the_same_combination_twice_in_one_request_is_rejected(): void
    {
        $product = Product::factory()->create();
        $color = $this->option($product, 'Color', ['Rojo']);
        $valueId = $color->values->first()->id;

        try {
            $this->generator->generate($product, [[$valueId], [$valueId]]);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('combinations.1', $e->errors());
        }

        $this->assertSame(0, $product->variants()->count());
    }

    public function test_an_option_with_no_values_blocks_generation(): void
    {
        $product = Product::factory()->create();
        $this->option($product, 'Color', ['Rojo']);
        ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Talla', 'position' => 1]);

        try {
            $this->generator->generate($product->fresh());
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Talla', $e->errors()['combinations'][0]);
        }
    }

    public function test_it_refuses_to_exceed_the_variant_cap(): void
    {
        config(['commerce.catalog.max_variants_per_product' => 3]);

        $product = Product::factory()->create();
        $this->option($product, 'Color', ['Rojo', 'Azul']);
        $this->option($product, 'Talla', ['M', 'L']);

        try {
            $this->generator->generate($product);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('máximo por producto es 3', $e->errors()['combinations'][0]);
        }

        $this->assertSame(0, $product->variants()->count());
    }

    // -----------------------------------------------------------------
    // SKUs and starting state
    // -----------------------------------------------------------------

    public function test_a_sku_collision_is_settled_with_a_suffix_that_counts_archived_variants(): void
    {
        $archived = ProductVariant::factory()->create(['sku' => 'CAMISA-ROJO']);
        $archived->delete();

        $product = Product::factory()->create(['slug' => 'camisa']);
        $this->option($product, 'Color', ['Rojo']);

        $this->generator->generate($product);

        $this->assertSame('CAMISA-ROJO-2', $product->variants()->sole()->sku);
    }

    public function test_the_sku_prefix_replaces_the_product_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa-manga-larga-de-algodon']);
        $this->option($product, 'Color', ['Rojo']);

        $this->generator->generate($product, null, 'CAM');

        $this->assertSame('CAM-ROJO', $product->variants()->sole()->sku);
    }

    public function test_generated_variants_start_empty_and_active(): void
    {
        $product = Product::factory()->create();
        $this->option($product, 'Color', ['Rojo']);

        $variant = $this->generator->generate($product)['created']->first();

        $this->assertSame(0, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
        $this->assertTrue($variant->is_active);
        $this->assertNull($variant->price_override);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function option(Product $product, string $name, array $values): ProductOption
    {
        $option = ProductOption::factory()->create([
            'product_id' => $product->id,
            'name' => $name,
            'position' => $product->options()->count(),
        ]);

        foreach (array_values($values) as $position => $value) {
            ProductOptionValue::factory()->create([
                'product_option_id' => $option->id,
                'value' => $value,
                'position' => $position,
            ]);
        }

        return $option->load('values');
    }
}
