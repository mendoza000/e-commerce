<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_top_level_categories_with_nested_children(): void
    {
        $parent = Category::factory()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $child = Category::factory()->create([
            'name' => 'Camisas',
            'slug' => 'camisas',
            'parent_id' => $parent->id,
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                [
                    'id' => $parent->id,
                    'name' => 'Ropa',
                    'slug' => 'ropa',
                    'children' => [
                        [
                            'id' => $child->id,
                            'name' => 'Camisas',
                            'slug' => 'camisas',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_category_without_children_returns_empty_children_array(): void
    {
        Category::factory()->create(['name' => 'Zapatos', 'slug' => 'zapatos']);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                ['slug' => 'zapatos', 'children' => []],
            ],
        ]);
    }

    public function test_returns_empty_data_when_no_categories_exist(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $response->assertExactJson(['data' => []]);
    }

    public function test_child_categories_are_not_returned_as_top_level_entries(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
