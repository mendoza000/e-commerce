<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantManagementTest extends TestCase
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

    // -----------------------------------------------------------------
    // Generating
    // -----------------------------------------------------------------

    public function test_it_generates_the_whole_grid_and_says_what_it_did(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $this->option($product, 'Color', ['Rojo', 'Azul']);
        $this->option($product, 'Talla', ['M', 'L']);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/variants")
            ->assertCreated()
            ->assertJsonCount(4, 'data.variants')
            ->assertJsonPath('meta.created', 4)
            ->assertJsonPath('meta.skipped', 0)
            ->assertJsonPath('data.variants.0.option_values.0.option_name', 'Color');
    }

    public function test_it_generates_only_the_selected_combinations(): void
    {
        $product = Product::factory()->create(['slug' => 'camisa']);
        $color = $this->option($product, 'Color', ['Rojo', 'Azul']);
        $talla = $this->option($product, 'Talla', ['M', 'L']);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/variants", [
                'combinations' => [
                    [
                        $color->values->firstWhere('value', 'Rojo')->id,
                        $talla->values->firstWhere('value', 'M')->id,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.sku', 'CAMISA-ROJO-M');
    }

    public function test_a_second_run_reports_the_combinations_it_skipped(): void
    {
        $product = Product::factory()->create();
        $this->option($product, 'Color', ['Rojo', 'Azul']);

        $owner = $this->owner();

        $this->actingAs($owner)->postJson("/api/admin/products/{$product->id}/variants")->assertCreated();

        $this->actingAs($owner)
            ->postJson("/api/admin/products/{$product->id}/variants")
            ->assertCreated()
            ->assertJsonPath('meta.created', 0)
            ->assertJsonPath('meta.skipped', 2);
    }

    public function test_generating_replaces_the_implicit_variant(): void
    {
        $owner = $this->owner();

        $productId = $this->actingAs($owner)
            ->postJson('/api/admin/products', ['name' => 'Camisa', 'base_price' => '10'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($owner)
            ->postJson("/api/admin/products/{$productId}/options", ['name' => 'Color'])
            ->assertCreated();

        $optionId = Product::query()->findOrFail($productId)->options()->sole()->id;

        $this->actingAs($owner)
            ->postJson("/api/admin/options/{$optionId}/values", ['value' => 'Rojo'])
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson("/api/admin/products/{$productId}/variants")
            ->assertCreated()
            ->assertJsonPath('meta.archived_implicit', 1)
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.sku', 'CAMISA-ROJO');
    }

    public function test_an_incomplete_combination_is_a_validation_error(): void
    {
        $product = Product::factory()->create();
        $color = $this->option($product, 'Color', ['Rojo']);
        $this->option($product, 'Talla', ['M']);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/variants", [
                'combinations' => [[$color->values->first()->id]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonStructure(['error' => ['fields' => ['combinations.0']]]);
    }

    public function test_the_variant_cap_is_enforced(): void
    {
        config(['commerce.catalog.max_variants_per_product' => 2]);

        $product = Product::factory()->create();
        $this->option($product, 'Color', ['Rojo', 'Azul']);
        $this->option($product, 'Talla', ['M', 'L']);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/variants")
            ->assertStatus(422);

        $this->assertSame(0, $product->variants()->count());
    }

    // -----------------------------------------------------------------
    // Editing a single variant
    // -----------------------------------------------------------------

    public function test_it_edits_the_sku_the_price_override_and_the_active_flag(): void
    {
        $variant = ProductVariant::factory()->create(['sku' => 'VIEJO', 'is_active' => true]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/variants/{$variant->id}", [
                'sku' => 'NUEVO-1',
                'price_override' => '25.500000',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.sku', 'NUEVO-1')
            ->assertJsonPath('data.price_override', '25.500000')
            ->assertJsonPath('data.effective_price', '25.500000')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_clearing_the_override_falls_back_to_the_product_price(): void
    {
        $product = Product::factory()->create(['base_price' => '19.000000']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_override' => '25.000000',
        ]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/variants/{$variant->id}", ['price_override' => null])
            ->assertOk()
            ->assertJsonPath('data.price_override', null)
            ->assertJsonPath('data.effective_price', '19.000000');
    }

    /**
     * The whole point of the adjustment endpoint: every unit that moves has to
     * leave a kardex row. A plain `stock` field here would be the way around
     * the ledger.
     */
    public function test_stock_cannot_be_written_through_the_variant_editor(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/variants/{$variant->id}", ['stock' => 999])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.stock.0',
                'El stock se cambia con un ajuste de inventario, que exige un motivo.',
            );

        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_a_sku_already_taken_by_an_archived_variant_is_rejected(): void
    {
        $archived = ProductVariant::factory()->create(['sku' => 'TOMADO']);
        $archived->delete();

        $variant = ProductVariant::factory()->create();

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/variants/{$variant->id}", ['sku' => 'TOMADO'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.sku.0', 'Ya existe una variante con ese SKU.');
    }

    // -----------------------------------------------------------------
    // Archiving a single variant
    // -----------------------------------------------------------------

    public function test_a_variant_is_archived(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/variants/{$variant->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('product_variants', ['id' => $variant->id]);
    }

    public function test_the_last_variant_of_a_product_is_not_archived(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/variants/{$variant->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertNotSoftDeleted('product_variants', ['id' => $variant->id]);
    }

    public function test_a_variant_holding_units_for_an_open_order_is_not_archived(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 5,
            'reserved_quantity' => 1,
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/variants/{$variant->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted('product_variants', ['id' => $variant->id]);
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_generate_or_edit_variants(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($staff)
            ->postJson("/api/admin/products/{$product->id}/variants")
            ->assertStatus(403);

        $this->actingAs($staff)
            ->patchJson("/api/admin/variants/{$variant->id}", ['sku' => 'X'])
            ->assertStatus(403);

        $this->actingAs($staff)
            ->deleteJson("/api/admin/variants/{$variant->id}")
            ->assertStatus(403);
    }
}
