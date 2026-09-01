<?php

namespace Tests\Unit\Notifications;

use App\Models\Currency;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\PaymentProofSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class PaymentProofSubmittedTest extends TestCase
{
    use RefreshDatabase;

    private function notification(array $orderOverrides = [], array $proofOverrides = []): PaymentProofSubmitted
    {
        $ves = Currency::factory()->create(['code' => 'VES']);

        $order = Order::factory()->create(array_merge([
            'order_number' => 'ORD-20260831-ABC123',
            'customer_name' => 'Juan Perez',
            'customer_phone' => '+584121234567',
            'payment_currency_id' => $ves->id,
            'payment_amount' => 197120.686466,
        ], $orderOverrides));

        $proof = PaymentProof::factory()->create(array_merge([
            'order_id' => $order->id,
            'original_name' => 'comprobante.jpg',
            'reference' => '0123456789',
        ], $proofOverrides));

        return new PaymentProofSubmitted($order, $proof);
    }

    private function mail(PaymentProofSubmitted $notification): MailMessage
    {
        return $notification->toMail(User::factory()->create());
    }

    /**
     * A slow mail server must never hold up the customer's upload request.
     */
    public function test_it_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->notification());
    }

    public function test_it_is_delivered_by_mail(): void
    {
        $this->assertSame(['mail'], $this->notification()->via(User::factory()->create()));
    }

    public function test_the_subject_names_the_order(): void
    {
        $mail = $this->mail($this->notification());

        $this->assertSame('Nuevo comprobante de pago — orden ORD-20260831-ABC123', $mail->subject);
    }

    public function test_the_body_carries_what_the_admin_needs_to_review_it(): void
    {
        $body = implode("\n", $this->mail($this->notification())->introLines);

        $this->assertStringContainsString('ORD-20260831-ABC123', $body);
        $this->assertStringContainsString('Juan Perez', $body);
        $this->assertStringContainsString('+584121234567', $body);
        $this->assertStringContainsString('VES', $body);
        $this->assertStringContainsString('comprobante.jpg', $body);
        $this->assertStringContainsString('0123456789', $body);
    }

    public function test_a_missing_reference_is_simply_left_out(): void
    {
        $body = implode("\n", $this->mail($this->notification(proofOverrides: ['reference' => null]))->introLines);

        $this->assertStringNotContainsString('Referencia informada', $body);
    }

    /**
     * The admin panel lives in the Next.js app, so the link must point there
     * rather than at this API.
     */
    public function test_the_action_links_to_the_order_in_the_frontend_admin(): void
    {
        config(['commerce.frontend_url' => 'https://tienda.test']);

        $mail = $this->mail($this->notification());

        $this->assertSame('https://tienda.test/admin/orders/ORD-20260831-ABC123', $mail->actionUrl);
    }

    public function test_a_trailing_slash_in_the_frontend_url_does_not_break_the_link(): void
    {
        config(['commerce.frontend_url' => 'https://tienda.test/']);

        $this->assertSame(
            'https://tienda.test/admin/orders/ORD-20260831-ABC123',
            $this->mail($this->notification())->actionUrl
        );
    }

    public function test_it_offers_a_wa_me_link_to_write_to_the_customer(): void
    {
        StoreSetting::create([
            'store_name' => 'Tienda Demo',
            'base_currency_id' => Currency::factory()->create(['code' => 'USD'])->id,
        ]);

        $outro = implode("\n", $this->mail($this->notification())->outroLines);

        // A plain wa.me link, not an API integration (PRD section 6).
        $this->assertStringContainsString('https://wa.me/584121234567', $outro);
        $this->assertStringContainsString(rawurlencode('Tienda Demo'), $outro);
    }

    public function test_the_whatsapp_number_is_stripped_of_formatting(): void
    {
        $outro = implode("\n", $this->mail(
            $this->notification(['customer_phone' => '+58 412-123 4567'])
        )->outroLines);

        $this->assertStringContainsString('https://wa.me/584121234567', $outro);
    }

    public function test_it_falls_back_to_a_generic_store_name_when_none_is_configured(): void
    {
        $outro = implode("\n", $this->mail($this->notification())->outroLines);

        $this->assertStringContainsString('wa.me', $outro);
        $this->assertStringContainsString(rawurlencode('la tienda'), $outro);
    }
}
