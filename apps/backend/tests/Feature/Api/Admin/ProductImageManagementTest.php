<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();

        // Catalogue images live on the public disk — served by the web server
        // through storage:link, not streamed through the API like proofs.
        Storage::fake('public');
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    // -----------------------------------------------------------------
    // Uploading
    // -----------------------------------------------------------------

    public function test_the_first_image_of_a_product_becomes_its_cover(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('foto.jpg', 2000, 1500),
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.0.position', 0);

        Storage::disk('public')->assertExists($response->json('data.0.path'));
    }

    public function test_uploads_are_re_encoded_and_filed_under_the_product(): void
    {
        $product = Product::factory()->create();

        $path = $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('nombre-del-cliente.png', 800, 600),
            ])
            ->assertCreated()
            ->json('data.0.path');

        // The client's filename never survives, and everything is stored as
        // JPEG by the shared ImageStorageService.
        $this->assertStringStartsWith("products/{$product->id}/", $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringNotContainsString('nombre-del-cliente', $path);
    }

    public function test_the_second_image_is_appended_without_stealing_the_cover(): void
    {
        $product = Product::factory()->create();
        $owner = $this->owner();

        $this->actingAs($owner)->postJson("/api/admin/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('a.jpg'),
        ])->assertCreated();

        $this->actingAs($owner)
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('b.jpg'),
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.is_primary', false)
            ->assertJsonPath('data.1.position', 1);
    }

    public function test_an_image_can_be_pinned_to_an_option_value(): void
    {
        $product = Product::factory()->create();
        $option = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color']);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'Rojo']);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('rojo.jpg'),
                'product_option_value_id' => $value->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.product_option_value_id', $value->id);
    }

    public function test_an_option_value_of_another_product_is_rejected(): void
    {
        $product = Product::factory()->create();
        $foreignOption = ProductOption::factory()->create(['product_id' => Product::factory()->create()->id]);
        $foreignValue = ProductOptionValue::factory()->create(['product_option_id' => $foreignOption->id]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('foto.jpg'),
                'product_option_value_id' => $foreignValue->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.product_option_value_id.0', 'Ese valor de opción no pertenece a este producto.');

        $this->assertSame(0, $product->images()->count());
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertSame(0, $product->images()->count());
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        config(['commerce.product_image.max_kilobytes' => 100]);

        $product = Product::factory()->create();

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('enorme.jpg')->size(500),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['image']]]);
    }

    public function test_a_product_cannot_pass_the_image_limit(): void
    {
        config(['commerce.catalog.max_images_per_product' => 1]);

        $product = Product::factory()->create();
        ProductImage::factory()->create(['product_id' => $product->id]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['image']]]);
    }

    // -----------------------------------------------------------------
    // Ordering and the cover
    // -----------------------------------------------------------------

    public function test_it_reorders_the_images_of_a_product(): void
    {
        $product = Product::factory()->create();
        $first = ProductImage::factory()->primary()->create(['product_id' => $product->id, 'position' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $product->id, 'position' => 1]);
        $third = ProductImage::factory()->create(['product_id' => $product->id, 'position' => 2]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images/reorder", [
                'images' => [$third->id, $first->id, $second->id],
            ])
            ->assertOk();

        $this->assertSame(0, $third->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);
    }

    public function test_a_partial_reorder_is_rejected(): void
    {
        $product = Product::factory()->create();
        $first = ProductImage::factory()->create(['product_id' => $product->id, 'position' => 0]);
        ProductImage::factory()->create(['product_id' => $product->id, 'position' => 1]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images/reorder", ['images' => [$first->id]])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.images.0',
                'La lista debe incluir exactamente una vez cada imagen de este producto.',
            );
    }

    public function test_an_image_of_another_product_cannot_be_slipped_into_the_reorder(): void
    {
        $product = Product::factory()->create();
        $mine = ProductImage::factory()->create(['product_id' => $product->id]);
        $foreign = ProductImage::factory()->create();

        $this->actingAs($this->owner())
            ->postJson("/api/admin/products/{$product->id}/images/reorder", [
                'images' => [$mine->id, $foreign->id],
            ])
            ->assertStatus(422);
    }

    public function test_making_an_image_primary_takes_the_flag_from_the_previous_one(): void
    {
        $product = Product::factory()->create();
        $old = ProductImage::factory()->primary()->create(['product_id' => $product->id, 'position' => 0]);
        $new = ProductImage::factory()->create(['product_id' => $product->id, 'position' => 1]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/images/{$new->id}/primary")
            ->assertOk()
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.is_primary', true);

        $this->assertFalse($old->fresh()->is_primary);
    }

    // -----------------------------------------------------------------
    // Deleting
    // -----------------------------------------------------------------

    public function test_deleting_the_cover_hands_the_flag_to_the_next_image(): void
    {
        $product = Product::factory()->create();
        $cover = ProductImage::factory()->primary()->create(['product_id' => $product->id, 'position' => 0]);
        $next = ProductImage::factory()->create(['product_id' => $product->id, 'position' => 1]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/images/{$cover->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseMissing('product_images', ['id' => $cover->id]);
        $this->assertTrue($next->fresh()->is_primary);
    }

    public function test_deleting_an_image_removes_the_file_behind_it(): void
    {
        $product = Product::factory()->create();
        $owner = $this->owner();

        $path = $this->actingAs($owner)
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->json('data.0.path');

        Storage::disk('public')->assertExists($path);

        $imageId = $product->images()->sole()->id;

        $this->actingAs($owner)->deleteJson("/api/admin/images/{$imageId}")->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_reads_the_images_but_does_not_change_them(): void
    {
        $staff = User::factory()->staff()->create();
        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);

        $this->actingAs($staff)->getJson("/api/admin/products/{$product->id}/images")->assertOk();

        $this->actingAs($staff)
            ->postJson("/api/admin/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertStatus(403);

        $this->actingAs($staff)
            ->postJson("/api/admin/products/{$product->id}/images/reorder", ['images' => [$image->id]])
            ->assertStatus(403);

        $this->actingAs($staff)->postJson("/api/admin/images/{$image->id}/primary")->assertStatus(403);
        $this->actingAs($staff)->deleteJson("/api/admin/images/{$image->id}")->assertStatus(403);
    }
}
