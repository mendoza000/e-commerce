<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalogue rules that live on the models rather than in a controller:
 * archiving, the "in use" checks that stop a delete, and the derived numbers
 * the panel reads.
 */
class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Archiving
    // -----------------------------------------------------------------

    public function test_archiving_a_product_archives_its_live_variants(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $product->archive();

        $this->assertSoftDeleted($product);
        $this->assertSoftDeleted($variant);
    }

    public function test_unarchiving_brings_the_product_and_its_variants_back(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $product->archive();
        $product->unarchive();

        $this->assertNotSoftDeleted($product);
        $this->assertNotSoftDeleted($variant);
    }

    public function test_archiving_leaves_another_products_variants_alone(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $neighbour = ProductVariant::factory()->create();

        $product->archive();

        $this->assertNotSoftDeleted($neighbour);
    }

    public function test_a_product_knows_when_an_open_order_is_holding_its_stock(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 5,
            'reserved_quantity' => 0,
        ]);

        $this->assertFalse($product->hasLiveReservations());

        $variant->update(['reserved_quantity' => 1]);

        $this->assertTrue($product->hasLiveReservations());
    }

    // -----------------------------------------------------------------
    // Derived numbers
    // -----------------------------------------------------------------

    public function test_available_stock_discounts_what_is_reserved(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 4]);

        $this->assertSame(6, $variant->availableStock());
    }

    /**
     * An out-of-band correction can leave reserved above stock, and a negative
     * "available" would read as a discount to every caller.
     */
    public function test_available_stock_never_goes_negative(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 2, 'reserved_quantity' => 5]);

        $this->assertSame(0, $variant->availableStock());
    }

    public function test_a_variant_without_option_values_is_the_implicit_one(): void
    {
        $product = Product::factory()->create();
        $implicit = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->assertTrue($implicit->isImplicit());

        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);
        $implicit->optionValues()->attach($value->id);

        $this->assertFalse($implicit->fresh()->isImplicit());
    }

    // -----------------------------------------------------------------
    // "In use" checks
    // -----------------------------------------------------------------

    public function test_an_option_and_its_value_know_when_a_variant_depends_on_them(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $this->assertFalse($option->isUsedByVariants());
        $this->assertFalse($value->isUsedByVariants());

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);

        $this->assertTrue($option->isUsedByVariants());
        $this->assertTrue($value->isUsedByVariants());
    }

    public function test_an_archived_variant_no_longer_holds_an_option_hostage(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);
        $variant->delete();

        $this->assertFalse($option->isUsedByVariants());
        $this->assertFalse($value->isUsedByVariants());
    }

    public function test_a_category_is_in_use_while_it_has_products_or_children(): void
    {
        $category = Category::factory()->create();

        $this->assertFalse($category->isInUse());

        $child = Category::factory()->create(['parent_id' => $category->id]);

        $this->assertTrue($category->isInUse());

        $child->delete();
        Product::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->fresh()->isInUse());
    }

    // -----------------------------------------------------------------
    // Slugs
    // -----------------------------------------------------------------

    public function test_a_free_slug_is_used_as_is(): void
    {
        $this->assertSame('camisa-de-lino', Product::uniqueSlug('Camisa de Lino'));
    }

    public function test_a_taken_slug_gets_the_first_free_suffix(): void
    {
        Product::factory()->create(['slug' => 'camisa']);
        Product::factory()->create(['slug' => 'camisa-2']);

        $this->assertSame('camisa-3', Product::uniqueSlug('Camisa'));
    }

    /**
     * The unique index counts soft-deleted rows, so a slug freed only in
     * Eloquent's eyes still fails on insert.
     */
    public function test_an_archived_product_still_holds_its_slug(): void
    {
        $archived = Product::factory()->create(['slug' => 'camisa']);
        $archived->delete();

        $this->assertSame('camisa-2', Product::uniqueSlug('Camisa'));
    }

    public function test_a_row_may_keep_its_own_slug_while_being_updated(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);

        $this->assertSame('camisa', Product::uniqueSlug('Camisa', $product->id));
    }

    /**
     * A name written entirely in characters Str::slug strips would otherwise
     * slugify to the empty string, and every such row would collide.
     */
    public function test_a_name_that_slugifies_to_nothing_falls_back_to_a_placeholder(): void
    {
        $this->assertSame('item', Category::uniqueSlug('***'));

        Category::factory()->create(['slug' => 'item']);

        $this->assertSame('item-2', Category::uniqueSlug('***'));
    }
}
