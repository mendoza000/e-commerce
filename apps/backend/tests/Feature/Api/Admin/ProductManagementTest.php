<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    // -----------------------------------------------------------------
    // Creating
    // -----------------------------------------------------------------

    public function test_creating_a_product_also_creates_its_implicit_variant(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/products', [
                'name' => 'Camisa Lisa',
                'base_price' => '19.500000',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'camisa-lisa')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.sku', 'CAMISA-LISA')
            ->assertJsonPath('data.variants.0.stock', 0);
    }

    public function test_a_repeated_name_gets_a_distinct_slug(): void
    {
        Product::factory()->create(['name' => 'Camisa', 'slug' => 'camisa']);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/products', ['name' => 'Camisa', 'base_price' => '10'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'camisa-2');
    }

    /**
     * The unique index does not forget an archived row, so neither may the
     * validation: otherwise the insert would fail with a 500 instead of a 422.
     */
    public function test_the_slug_of_an_archived_product_is_still_taken(): void
    {
        $archived = Product::factory()->create(['slug' => 'camisa']);
        $archived->delete();

        $this->actingAs($this->owner())
            ->postJson('/api/admin/products', ['name' => 'Camisa', 'slug' => 'camisa', 'base_price' => '10'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.slug.0', 'Ya existe un producto con esa URL.');
    }

    public function test_the_price_is_validated_against_the_decimal_column(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/products', ['name' => 'Camisa', 'base_price' => '10.1234567'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.base_price.0', 'El precio admite hasta 6 decimales.');
    }

    // -----------------------------------------------------------------
    // Listing
    // -----------------------------------------------------------------

    public function test_the_listing_carries_the_counts_and_the_stock_across_variants(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 4]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 6]);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('data.0.variants_count', 2)
            ->assertJsonPath('data.0.total_stock', 10);
    }

    public function test_it_filters_by_status_category_and_search(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['name' => 'Camisa Azul', 'category_id' => $category->id]);
        Product::factory()->create(['name' => 'Pantalón Negro', 'is_active' => false]);

        $owner = $this->owner();

        $this->actingAs($owner)->getJson('/api/admin/products?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pantalón Negro');

        $this->actingAs($owner)->getJson("/api/admin/products?category_id={$category->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Camisa Azul');

        $this->actingAs($owner)->getJson('/api/admin/products?search=camisa')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_search_term_is_escaped_before_it_reaches_ilike(): void
    {
        Product::factory()->create(['name' => 'Camisa']);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/products?search=_')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_product_is_findable_by_the_sku_of_one_of_its_variants(): void
    {
        $product = Product::factory()->create(['name' => 'Camisa']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'ABC-123']);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/products?search=ABC-123')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_archived_products_are_hidden_unless_asked_for(): void
    {
        $live = Product::factory()->create(['name' => 'Vive']);
        $archived = Product::factory()->create(['name' => 'Archivado']);
        $archived->archive();

        $owner = $this->owner();

        $this->actingAs($owner)->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $live->id);

        $this->actingAs($owner)->getJson('/api/admin/products?trashed=with')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($owner)->getJson('/api/admin/products?trashed=only')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archived->id)
            ->assertJsonPath('data.0.is_archived', true);
    }

    // -----------------------------------------------------------------
    // Updating
    // -----------------------------------------------------------------

    public function test_renaming_a_product_does_not_change_its_public_url(): void
    {
        $product = Product::factory()->create(['name' => 'Camisa', 'slug' => 'camisa']);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/products/{$product->id}", ['name' => 'Camisa Premium'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Camisa Premium')
            ->assertJsonPath('data.slug', 'camisa');
    }

    public function test_the_slug_can_be_changed_on_purpose(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/products/{$product->id}", ['slug' => 'camisa-premium'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'camisa-premium');
    }

    public function test_a_product_can_be_unpublished_without_being_archived(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/products/{$product->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_archived', false);
    }

    // -----------------------------------------------------------------
    // Archiving and restoring
    // -----------------------------------------------------------------

    public function test_archiving_a_product_archives_its_live_variants_too(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
    }

    public function test_a_product_with_units_reserved_by_open_orders_is_not_archived(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 5,
            'reserved_quantity' => 2,
        ]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    /**
     * Everything comes back, including variants that had been retired one by
     * one before the product was archived — see Product::unarchive() for why
     * telling them apart is not possible with a second-precision column.
     */
    public function test_restoring_brings_the_product_back_with_all_of_its_variants(): void
    {
        $product = Product::factory()->create();
        $kept = ProductVariant::factory()->create(['product_id' => $product->id]);
        $retiredBefore = ProductVariant::factory()->create(['product_id' => $product->id]);

        $retiredBefore->delete();
        $product->archive();

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.is_archived', false)
            ->assertJsonCount(2, 'data.variants');

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
        $this->assertNotSoftDeleted('product_variants', ['id' => $kept->id]);
        $this->assertNotSoftDeleted('product_variants', ['id' => $retiredBefore->id]);
    }

    /**
     * The count is what tells the panel which option values are safe to
     * delete. It is a nested withCount, so a broken eager load would drop it
     * silently rather than fail.
     */
    public function test_the_detail_says_how_many_variants_depend_on_each_option_value(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color']);
        $used = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'Rojo', 'position' => 0]);
        ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'Azul', 'position' => 1]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($used->id);

        $this->actingAs($this->owner())
            ->getJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.options.0.values.0.value', 'Rojo')
            ->assertJsonPath('data.options.0.values.0.variants_count', 1)
            ->assertJsonPath('data.options.0.values.1.variants_count', 0);
    }

    public function test_an_archived_product_is_still_readable(): void
    {
        $product = Product::factory()->create();
        $product->archive();

        $this->actingAs($this->owner())
            ->getJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_reads_the_catalogue_but_does_not_write_it(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create();

        $this->actingAs($staff)->getJson('/api/admin/products')->assertOk();
        $this->actingAs($staff)->getJson("/api/admin/products/{$product->id}")->assertOk();

        $this->actingAs($staff)
            ->postJson('/api/admin/products', ['name' => 'Nuevo', 'base_price' => '10'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->actingAs($staff)
            ->patchJson("/api/admin/products/{$product->id}", ['name' => 'Otro'])
            ->assertStatus(403);

        $this->actingAs($staff)
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertStatus(403);

        $this->actingAs($staff)
            ->postJson("/api/admin/products/{$product->id}/restore")
            ->assertStatus(403);
    }

    public function test_an_anonymous_request_never_reaches_the_catalogue(): void
    {
        $this->getJson('/api/admin/products')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }
}
