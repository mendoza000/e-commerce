<?php

namespace Tests\Unit\Services;

use App\Services\ImageStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The upload pipeline both payment proofs and catalogue images now share.
 */
class ImageStorageServiceTest extends TestCase
{
    private ImageStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = new ImageStorageService;
    }

    public function test_it_re_encodes_images_to_jpeg_under_a_name_the_uploader_did_not_choose(): void
    {
        $path = $this->service->store(
            UploadedFile::fake()->image('mi-foto-personal.png', 800, 600),
            'public',
            'products/1',
            1600,
            82,
        );

        $this->assertStringStartsWith('products/1/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringNotContainsString('mi-foto-personal', $path);

        Storage::disk('public')->assertExists($path);
    }

    public function test_it_downscales_an_oversized_image(): void
    {
        $path = $this->service->store(
            UploadedFile::fake()->image('grande.jpg', 4000, 3000),
            'public',
            'products/1',
            800,
            82,
        );

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(800, $width);
    }

    /**
     * scaleDown never upscales: a small image is left at its own size rather
     * than blown up into a blurry one.
     */
    public function test_it_leaves_a_small_image_at_its_own_size(): void
    {
        $path = $this->service->store(
            UploadedFile::fake()->image('chica.jpg', 320, 240),
            'public',
            'products/1',
            1600,
            82,
        );

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(320, $width);
    }

    /**
     * PDFs are what payment proofs bring; re-encoding one would destroy it.
     */
    public function test_it_stores_a_non_image_untouched(): void
    {
        Storage::fake('local');

        $path = $this->service->store(
            UploadedFile::fake()->create('comprobante.pdf', 40, 'application/pdf'),
            'local',
            'payment-proofs/ORD-1',
            1600,
            75,
        );

        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_the_directory_is_normalised_before_use(): void
    {
        $path = $this->service->store(
            UploadedFile::fake()->image('foto.jpg'),
            'public',
            '/products/7/',
            1600,
            82,
        );

        $this->assertStringStartsWith('products/7/', $path);
    }

    public function test_deleting_a_file_that_is_already_gone_is_not_an_error(): void
    {
        $this->service->delete('public', 'products/1/no-existe.jpg');

        Storage::disk('public')->assertMissing('products/1/no-existe.jpg');
    }
}
