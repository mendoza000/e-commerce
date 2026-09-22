<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_an_order(): void
    {
        $order = Order::factory()->create();
        $proof = PaymentProof::factory()->create(['order_id' => $order->id]);

        $this->assertTrue($proof->order->is($order));
    }

    public function test_submitted_at_is_cast_to_a_date(): void
    {
        $proof = PaymentProof::factory()->create(['submitted_at' => '2026-08-31 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $proof->fresh()->submitted_at);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function mimeTypeProvider(): array
    {
        return [
            'jpeg' => ['image/jpeg', true],
            'png' => ['image/png', true],
            'webp' => ['image/webp', true],
            'pdf' => ['application/pdf', false],
            'octet stream' => ['application/octet-stream', false],
        ];
    }

    /**
     * The admin panel renders images inline and PDFs as a download link, so
     * this is the flag that decides which.
     */
    #[DataProvider('mimeTypeProvider')]
    public function test_is_image_recognises_the_stored_mime_type(string $mimeType, bool $expected): void
    {
        $proof = PaymentProof::factory()->make(['mime_type' => $mimeType]);

        $this->assertSame($expected, $proof->isImage());
    }

    public function test_proofs_are_deleted_with_their_order(): void
    {
        $order = Order::factory()->create();
        PaymentProof::factory()->count(2)->create(['order_id' => $order->id]);

        $order->delete();

        $this->assertSame(0, PaymentProof::query()->count());
    }

    public function test_the_latest_proof_is_the_most_recently_submitted_one(): void
    {
        $order = Order::factory()->create();

        PaymentProof::factory()->create([
            'order_id' => $order->id,
            'original_name' => 'primero.jpg',
            'submitted_at' => now()->subHour(),
        ]);
        PaymentProof::factory()->create([
            'order_id' => $order->id,
            'original_name' => 'segundo.jpg',
            'submitted_at' => now(),
        ]);

        $this->assertSame('segundo.jpg', $order->latestPaymentProof->original_name);
        $this->assertSame(2, $order->paymentProofs()->count());
    }
}
