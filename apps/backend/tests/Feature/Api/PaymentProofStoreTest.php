<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Notifications\PaymentProofSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
    }

    private function order(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'document_number' => '12345678',
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'reservation_expires_at' => now()->addMinutes(45),
        ], $overrides));
    }

    private function url(Order $order, string $documentNumber = '12345678'): string
    {
        return "/api/orders/{$order->order_number}/payment-proof?document_number={$documentNumber}";
    }

    public function test_a_guest_can_upload_a_proof_with_the_right_document_number(): void
    {
        $order = $this->order();

        $response = $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->image('comprobante.jpg', 2400, 1600),
            'reference' => '0123456789',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'payment_submitted');
        $response->assertJsonPath('data.payment_proof.reference', '0123456789');

        $proof = $order->paymentProofs()->firstOrFail();

        Storage::disk('local')->assertExists($proof->path);
        $this->assertSame(OrderStatus::PaymentSubmitted, $order->fresh()->status);
    }

    public function test_uploaded_images_are_downscaled_and_re_encoded(): void
    {
        config(['commerce.payment_proof.image_max_width' => 800]);

        $order = $this->order();

        $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->image('comprobante.png', 2400, 1600),
        ])->assertCreated();

        $proof = $order->paymentProofs()->firstOrFail();

        $this->assertSame('image/jpeg', $proof->mime_type);
        $this->assertStringEndsWith('.jpg', $proof->path);

        [$width] = getimagesizefromstring(Storage::disk('local')->get($proof->path));
        $this->assertSame(800, $width);
    }

    public function test_a_pdf_is_stored_untouched(): void
    {
        $order = $this->order();

        $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 120, 'application/pdf'),
        ])->assertCreated();

        $this->assertSame('comprobante.pdf', $order->paymentProofs()->firstOrFail()->original_name);
    }

    public function test_the_proof_extends_the_reservation_for_admin_review(): void
    {
        config(['commerce.payment_review_minutes' => 4320]);

        $order = $this->order(['reservation_expires_at' => now()->addMinutes(10)]);

        $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertCreated();

        $this->assertTrue($order->fresh()->reservation_expires_at->isAfter(now()->addDays(2)));
    }

    public function test_admins_are_notified(): void
    {
        $admin = User::factory()->create();
        $order = $this->order();

        $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertCreated();

        Notification::assertSentTo($admin, PaymentProofSubmitted::class);
    }

    public function test_the_authenticated_owner_needs_no_document_number(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->order(['customer_id' => $customer->id]);
        $token = $customer->createToken('checkout')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/orders/{$order->order_number}/payment-proof", [
                'proof' => UploadedFile::fake()->image('comprobante.jpg'),
            ])
            ->assertCreated();
    }

    public function test_a_wrong_document_number_gets_a_404_not_a_403(): void
    {
        $order = $this->order();

        // A 403 would confirm the order number exists.
        $this->postJson($this->url($order, '99999999'), [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ])->assertNotFound();

        $this->assertSame(0, $order->paymentProofs()->count());
    }

    public function test_a_paid_order_rejects_further_proofs(): void
    {
        $order = $this->order(['status' => OrderStatus::Paid]);

        $response = $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['fields' => ['proof']]]);
    }

    public function test_a_re_upload_is_accepted_and_keeps_the_previous_proof(): void
    {
        $order = $this->order();

        foreach (['primero.jpg', 'segundo.jpg'] as $name) {
            $this->postJson($this->url($order), [
                'proof' => UploadedFile::fake()->image($name),
            ])->assertCreated();
        }

        $this->assertSame(2, $order->paymentProofs()->count());
        $this->assertSame('segundo.jpg', $order->fresh()->latestPaymentProof->original_name);
        // The status only ever changed once.
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_an_unsupported_file_type_is_rejected(): void
    {
        $order = $this->order();

        $response = $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->create('malicioso.exe', 10),
        ]);

        $response->assertStatus(422);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_a_file_over_the_size_limit_is_rejected(): void
    {
        config(['commerce.payment_proof.max_kilobytes' => 100]);

        $order = $this->order();

        $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->create('grande.pdf', 500, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_the_proof_is_required(): void
    {
        $order = $this->order();

        $this->postJson($this->url($order), [])->assertStatus(422);
    }

    public function test_the_storage_path_is_never_exposed(): void
    {
        $order = $this->order();

        $response = $this->postJson($this->url($order), [
            'proof' => UploadedFile::fake()->image('comprobante.jpg'),
        ]);

        $response->assertCreated();
        $response->assertJsonMissingPath('data.payment_proof.path');
        $response->assertJsonMissingPath('data.payment_proof.disk');
    }
}
