<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();
    }

    // -----------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------

    public function test_it_lists_categories_with_the_counts_that_explain_a_refused_delete(): void
    {
        $parent = Category::factory()->create(['name' => 'Ropa']);
        Category::factory()->create(['name' => 'Camisas', 'parent_id' => $parent->id]);
        Product::factory()->create(['category_id' => $parent->id]);

        $response = $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ropa = collect($response->json('data'))->firstWhere('name', 'Ropa');

        $this->assertSame(1, $ropa['products_count']);
        $this->assertSame(1, $ropa['children_count']);
    }

    public function test_staff_may_read_the_catalogue(): void
    {
        Category::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->getJson('/api/admin/categories')
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------

    public function test_the_slug_is_derived_from_the_name_when_none_is_sent(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->postJson('/api/admin/categories', ['name' => 'Ropa de Playa'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'ropa-de-playa');
    }

    public function test_a_repeated_name_gets_a_distinct_slug_instead_of_an_error(): void
    {
        Category::factory()->create(['name' => 'Ropa', 'slug' => 'ropa']);

        $this->actingAs(User::factory()->owner()->create())
            ->postJson('/api/admin/categories', ['name' => 'Ropa'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'ropa-2');
    }

    public function test_a_slug_sent_by_hand_still_has_to_be_free(): void
    {
        Category::factory()->create(['slug' => 'ropa']);

        $this->actingAs(User::factory()->owner()->create())
            ->postJson('/api/admin/categories', ['name' => 'Otra', 'slug' => 'ropa'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.fields.slug.0', 'Ya existe una categoría con esa URL.');
    }

    public function test_it_updates_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Ropa', 'slug' => 'ropa']);

        $this->actingAs(User::factory()->owner()->create())
            ->patchJson("/api/admin/categories/{$category->id}", ['name' => 'Indumentaria'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Indumentaria');

        // Renaming does not re-slug: the slug is a public URL.
        $this->assertSame('ropa', $category->fresh()->slug);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->owner()->create())
            ->patchJson("/api/admin/categories/{$category->id}", ['parent_id' => $category->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_a_category_cannot_hang_from_one_of_its_own_descendants(): void
    {
        $grandparent = Category::factory()->create();
        $parent = Category::factory()->create(['parent_id' => $grandparent->id]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs(User::factory()->owner()->create())
            ->patchJson("/api/admin/categories/{$grandparent->id}", ['parent_id' => $child->id])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.parent_id.0', 'Una categoría no puede colgar de sí misma ni de una de sus descendientes.');
    }

    // -----------------------------------------------------------------
    // Deleting
    // -----------------------------------------------------------------

    public function test_a_category_with_products_is_not_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs(User::factory()->owner()->create())
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_a_category_with_subcategories_is_not_deleted(): void
    {
        $category = Category::factory()->create();
        Category::factory()->create(['parent_id' => $category->id]);

        $this->actingAs(User::factory()->owner()->create())
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_an_empty_category_is_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->owner()->create())
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_write_the_catalogue(): void
    {
        $staff = User::factory()->staff()->create();
        $category = Category::factory()->create();

        $this->actingAs($staff)
            ->postJson('/api/admin/categories', ['name' => 'Nueva'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->actingAs($staff)
            ->patchJson("/api/admin/categories/{$category->id}", ['name' => 'Otra'])
            ->assertStatus(403);

        $this->actingAs($staff)
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
