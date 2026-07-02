<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_active_products(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_filters_by_category_slug(): void
    {
        $shoes = Category::factory()->create(['slug' => 'zapatos']);
        $shirts = Category::factory()->create(['slug' => 'camisas']);

        Product::factory()->create(['category_id' => $shoes->id, 'is_active' => true]);
        Product::factory()->create(['category_id' => $shirts->id, 'is_active' => true]);

        $response = $this->getJson('/api/products?category=zapatos');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame($shoes->id, $response->json('data.0.category.id'));
    }

    public function test_search_matches_partial_case_insensitive_name(): void
    {
        Product::factory()->create(['name' => 'Camisa Roja', 'is_active' => true]);
        Product::factory()->create(['name' => 'Pantalón Azul', 'is_active' => true]);

        $response = $this->getJson('/api/products?search=camisa');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('Camisa Roja', $response->json('data.0.name'));
    }

    public function test_inactive_products_never_appear(): void
    {
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_returns_empty_data_when_no_products_match(): void
    {
        $response = $this->getJson('/api/products?search=nonexistent');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_product_without_category_returns_null_category(): void
    {
        Product::factory()->create(['category_id' => null, 'is_active' => true]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $this->assertNull($response->json('data.0.category'));
    }

    public function test_invalid_per_page_returns_standardized_validation_error(): void
    {
        $response = $this->getJson('/api/products?per_page=500');

        $response->assertStatus(422);
        $response->assertJson([
            'error' => ['code' => 'validation_error'],
        ]);
        $response->assertJsonStructure(['error' => ['fields' => ['per_page']]]);
    }
}
