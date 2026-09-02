<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionManagementTest extends TestCase
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
    // Options
    // -----------------------------------------------------------------

    public function test_it_creates_an_option_and_appends_it_to_the_end(): void
    {
        $product = Product::factory()->create();
        ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color', 'position' => 0]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/options", ['name' => 'Talla'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Talla')
            ->assertJsonPath('data.position', 1);
    }

    public function test_the_same_option_name_twice_on_one_product_is_rejected(): void
    {
        $product = Product::factory()->create();
        ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color']);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/options", ['name' => 'Color'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.name.0', 'Este producto ya tiene una opción con ese nombre.');
    }

    /**
     * The variants would otherwise be silently undefined on the new axis: a
     * "Rojo-M" that says nothing about Material.
     */
    public function test_an_option_cannot_be_added_once_variants_are_built_on_the_grid(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color']);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/options", ['name' => 'Talla'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    /**
     * The implicit variant is not a combination, so it is not in the way: the
     * generator replaces it as soon as real combinations exist.
     */
    public function test_the_implicit_variant_does_not_block_the_first_option(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/options", ['name' => 'Color'])
            ->assertCreated();
    }

    public function test_an_option_can_always_be_renamed(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color']);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/options/{$option->id}", ['name' => 'Colour'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Colour');
    }

    public function test_an_option_in_use_by_variants_is_not_deleted(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/options/{$option->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertDatabaseHas('product_options', ['id' => $option->id]);
    }

    public function test_an_unused_option_is_deleted_with_its_values(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/options/{$option->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_options', ['id' => $option->id]);
        // product_option_values cascades on delete.
        $this->assertDatabaseMissing('product_option_values', ['id' => $value->id]);
    }

    // -----------------------------------------------------------------
    // Values
    // -----------------------------------------------------------------

    public function test_it_creates_values_and_appends_them_to_the_end(): void
    {
        $option = ProductOption::factory()->create();
        ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'Rojo', 'position' => 0]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/options/{$option->id}/values", ['value' => 'Azul'])
            ->assertCreated()
            ->assertJsonPath('data.value', 'Azul')
            ->assertJsonPath('data.position', 1);
    }

    /**
     * Unlike an option, a value can be added at any time: the existing
     * variants stay perfectly well defined and the generator picks up the new
     * combinations on its next run.
     */
    public function test_a_value_can_be_added_even_with_variants_already_generated(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'Rojo']);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/options/{$option->id}/values", ['value' => 'Azul'])
            ->assertCreated();
    }

    public function test_the_same_value_twice_on_one_option_is_rejected(): void
    {
        $option = ProductOption::factory()->create();
        ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'Rojo']);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/options/{$option->id}/values", ['value' => 'Rojo'])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.value.0', 'Esta opción ya tiene ese valor.');
    }

    public function test_a_value_in_use_by_variants_is_not_deleted(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/option-values/{$value->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('product_option_values', ['id' => $value->id]);
    }

    public function test_an_unused_value_is_deleted(): void
    {
        $value = ProductOptionValue::factory()->create();

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/option-values/{$value->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_option_values', ['id' => $value->id]);
    }

    public function test_a_value_is_renamed(): void
    {
        $value = ProductOptionValue::factory()->create(['value' => 'Rojo']);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/option-values/{$value->id}", ['value' => 'Rojo Vino'])
            ->assertOk()
            ->assertJsonPath('data.value', 'Rojo Vino');
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_touch_options_or_values(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);

        $this->actingAs($staff)
            ->postJson("/api/admin/products/{$product->id}/options", ['name' => 'Talla'])
            ->assertStatus(403);

        $this->actingAs($staff)->patchJson("/api/admin/options/{$option->id}", ['name' => 'X'])->assertStatus(403);
        $this->actingAs($staff)->deleteJson("/api/admin/options/{$option->id}")->assertStatus(403);

        $this->actingAs($staff)
            ->postJson("/api/admin/options/{$option->id}/values", ['value' => 'X'])
            ->assertStatus(403);

        $this->actingAs($staff)->patchJson("/api/admin/option-values/{$value->id}", ['value' => 'X'])->assertStatus(403);
        $this->actingAs($staff)->deleteJson("/api/admin/option-values/{$value->id}")->assertStatus(403);
    }
}
