<?php

namespace Tests\Unit\Notifications;

use App\Domain\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class OrderStatusUpdatedTest extends TestCase
{
    use RefreshDatabase;

    private function notification(array $overrides = []): OrderStatusUpdated
    {
        $ves = Currency::factory()->create(['code' => 'VES']);

        $order = Order::factory()->create(array_merge([
            'order_number' => 'ORD-20260902-ABC123',
            'customer_name' => 'Juan Perez',
            'status' => OrderStatus::Paid,
            'payment_currency_id' => $ves->id,
            'payment_amount' => 197120.686466,
        ], $overrides));

        return new OrderStatusUpdated($order);
    }

    private function mail(OrderStatusUpdated $notification): MailMessage
    {
        return $notification->toMail(Customer::factory()->create());
    }

    public function test_it_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->notification());
    }

    public function test_it_is_delivered_by_mail(): void
    {
        $this->assertSame(['mail'], $this->notification()->via(Customer::factory()->create()));
    }

    public function test_the_subject_names_the_order_and_status(): void
    {
        $mail = $this->mail($this->notification());

        $this->assertSame('Tu orden ORD-20260902-ABC123 — Pagada', $mail->subject);
    }

    public function test_the_body_greets_the_customer_and_states_the_total(): void
    {
        $mail = $this->mail($this->notification());
        $body = implode("\n", $mail->introLines);

        $this->assertStringContainsString('ORD-20260902-ABC123', $body);
        $this->assertStringContainsString('VES', $body);
        $this->assertSame('Hola Juan Perez,', $mail->greeting);
    }

    public function test_a_shipped_order_includes_the_courier_and_tracking_code_when_present(): void
    {
        $body = implode("\n", $this->mail($this->notification([
            'status' => OrderStatus::Shipped,
            'courier' => 'MRW',
            'tracking_code' => 'ABC123',
        ]))->introLines);

        $this->assertStringContainsString('MRW', $body);
        $this->assertStringContainsString('ABC123', $body);
    }

    public function test_a_shipped_order_with_no_courier_details_omits_them(): void
    {
        $body = implode("\n", $this->mail($this->notification(['status' => OrderStatus::Shipped]))->introLines);

        $this->assertStringNotContainsString('Courier:', $body);
        $this->assertStringNotContainsString('Número de guía:', $body);
    }

    public function test_a_paid_order_never_shows_courier_fields_even_if_somehow_set(): void
    {
        $body = implode("\n", $this->mail($this->notification([
            'status' => OrderStatus::Paid,
            'courier' => 'MRW',
        ]))->introLines);

        $this->assertStringNotContainsString('Courier:', $body);
    }

    /**
     * The storefront lives in the frontend app (docs/decisions.md), so the
     * link has to point there, not at this API.
     */
    public function test_the_action_links_to_the_order_in_the_storefront(): void
    {
        config(['commerce.frontend_url' => 'https://tienda.test']);

        $mail = $this->mail($this->notification());

        $this->assertSame('https://tienda.test/orders/ORD-20260902-ABC123', $mail->actionUrl);
    }

    public function test_it_offers_a_wa_me_link_to_the_stores_own_number(): void
    {
        StoreSetting::create([
            'store_name' => 'Tienda Demo',
            'base_currency_id' => Currency::factory()->create(['code' => 'USD'])->id,
            'whatsapp_number' => '+584121234567',
        ]);

        $outro = implode("\n", $this->mail($this->notification())->outroLines);

        $this->assertStringContainsString('https://wa.me/584121234567', $outro);
        $this->assertStringContainsString('ORD-20260902-ABC123', $outro);
    }

    public function test_no_whatsapp_line_when_the_store_has_no_number_configured(): void
    {
        StoreSetting::create([
            'store_name' => 'Tienda Demo',
            'base_currency_id' => Currency::factory()->create(['code' => 'USD'])->id,
        ]);

        $outro = implode("\n", $this->mail($this->notification())->outroLines);

        $this->assertStringNotContainsString('wa.me', $outro);
    }
}
