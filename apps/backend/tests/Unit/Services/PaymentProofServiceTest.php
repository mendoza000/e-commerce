<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Notifications\PaymentProofSubmitted;
use App\Services\PaymentProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentProofService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->service = new PaymentProofService;
    }

    private function order(): Order
    {
        return Order::factory()->create([
            'order_number' => 'ORD-20260831-ABC123',
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'reservation_expires_at' => now()->addMinutes(45),
        ]);
    }

    public function test_it_records_the_file_metadata_against_the_order(): void
    {
        $order = $this->order();

        $proof = $this->service->store(
            $order,
            UploadedFile::fake()->image('comprobante.jpg', 1000, 800),
            '0123456789'
        );

        $this->assertTrue($proof->order->is($order));
        $this->assertSame('comprobante.jpg', $proof->original_name);
        $this->assertSame('0123456789', $proof->reference);
        $this->assertSame('local', $proof->disk);
        $this->assertNotNull($proof->submitted_at);
        $this->assertGreaterThan(0, $proof->size_bytes);
    }

    public function test_the_reference_is_optional(): void
    {
        $proof = $this->service->store($this->order(), UploadedFile::fake()->image('c.jpg'), null);

        $this->assertNull($proof->reference);
    }

    public function test_files_are_filed_under_the_order_number(): void
    {
        $order = $this->order();

        $proof = $this->service->store($order, UploadedFile::fake()->image('c.jpg'), null);

        $this->assertStringStartsWith("payment-proofs/{$order->order_number}/", $proof->path);
        Storage::disk('local')->assertExists($proof->path);
    }

    public function test_the_stored_size_is_the_compressed_size_not_the_upload_size(): void
    {
        $proof = $this->service->store(
            $this->order(),
            UploadedFile::fake()->image('grande.png', 3000, 2000),
            null
        );

        $this->assertSame(
            strlen(Storage::disk('local')->get($proof->path)),
            $proof->size_bytes
        );
    }

    public function test_images_are_downscaled_to_the_configured_width(): void
    {
        config(['commerce.payment_proof.image_max_width' => 900]);

        $proof = $this->service->store(
            $this->order(),
            UploadedFile::fake()->image('grande.jpg', 3000, 2000),
            null
        );

        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($proof->path));

        $this->assertSame(900, $width);
        // scaleDown keeps the aspect ratio: 3000x2000 => 900x600.
        $this->assertSame(600, $height);
    }

    public function test_an_image_smaller_than_the_limit_is_not_upscaled(): void
    {
        config(['commerce.payment_proof.image_max_width' => 1600]);

        $proof = $this->service->store(
            $this->order(),
            UploadedFile::fake()->image('pequena.jpg', 400, 300),
            null
        );

        [$width] = getimagesizefromstring(Storage::disk('local')->get($proof->path));

        $this->assertSame(400, $width);
    }

    public function test_every_image_is_re_encoded_as_jpeg(): void
    {
        $proof = $this->service->store($this->order(), UploadedFile::fake()->image('c.png'), null);

        $this->assertSame('image/jpeg', $proof->mime_type);
        $this->assertStringEndsWith('.jpg', $proof->path);
    }

    public function test_a_pdf_is_stored_without_being_touched(): void
    {
        $proof = $this->service->store(
            $this->order(),
            UploadedFile::fake()->create('comprobante.pdf', 40, 'application/pdf'),
            null
        );

        $this->assertSame('application/pdf', $proof->mime_type);
        $this->assertStringEndsNotWith('.jpg', $proof->path);
        Storage::disk('local')->assertExists($proof->path);
    }

    public function test_storing_a_proof_moves_the_order_to_payment_submitted(): void
    {
        $order = $this->order();

        $this->service->store($order, UploadedFile::fake()->image('c.jpg'), null);

        $this->assertSame(OrderStatus::PaymentSubmitted, $order->fresh()->status);
    }

    public function test_two_uploads_produce_two_distinct_files(): void
    {
        $order = $this->order();

        $first = $this->service->store($order, UploadedFile::fake()->image('a.jpg'), null);
        $second = $this->service->store($order, UploadedFile::fake()->image('b.jpg'), null);

        $this->assertNotSame($first->path, $second->path);
        Storage::disk('local')->assertExists($first->path);
        Storage::disk('local')->assertExists($second->path);
    }

    public function test_it_notifies_every_admin_user(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();

        $this->service->store($this->order(), UploadedFile::fake()->image('c.jpg'), null);

        Notification::assertSentTo([$owner, $staff], PaymentProofSubmitted::class);
    }

    public function test_a_store_with_no_admin_users_still_accepts_the_upload(): void
    {
        $proof = $this->service->store($this->order(), UploadedFile::fake()->image('c.jpg'), null);

        $this->assertNotNull($proof->id);
        Notification::assertNothingSent();
    }

    public function test_it_honours_a_different_configured_disk(): void
    {
        Storage::fake('proofs');
        config(['commerce.payment_proof.disk' => 'proofs']);

        $proof = $this->service->store($this->order(), UploadedFile::fake()->image('c.jpg'), null);

        $this->assertSame('proofs', $proof->disk);
        Storage::disk('proofs')->assertExists($proof->path);
        Storage::disk('local')->assertMissing($proof->path);
    }
}
