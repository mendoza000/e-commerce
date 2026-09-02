<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Notifications\OrderStatusUpdated;
use App\Services\CustomerNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->service = new CustomerNotificationService;
    }

    public function test_it_notifies_a_registered_customer_with_an_email_on_paid(): void
    {
        $customer = Customer::factory()->create(['email' => 'juan@example.test']);
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Paid]);

        $this->service->notifyStatusChange($order);

        Notification::assertSentTo($customer, OrderStatusUpdated::class);
    }

    public function test_it_notifies_on_shipped_and_delivered_too(): void
    {
        $customer = Customer::factory()->create(['email' => 'juan@example.test']);

        foreach ([OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
            $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => $status]);

            $this->service->notifyStatusChange($order);
        }

        Notification::assertSentToTimes($customer, OrderStatusUpdated::class, 2);
    }

    public function test_it_does_nothing_for_statuses_outside_the_notifiable_set(): void
    {
        $customer = Customer::factory()->create(['email' => 'juan@example.test']);

        foreach ([OrderStatus::PendingPayment, OrderStatus::PaymentSubmitted, OrderStatus::Preparing, OrderStatus::Cancelled] as $status) {
            $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => $status]);

            $this->service->notifyStatusChange($order);
        }

        Notification::assertNothingSent();
    }

    /**
     * A guest order never captures an email, and this is the only channel this
     * notification can use today — see docs/decisions.md.
     */
    public function test_a_guest_order_is_silently_skipped(): void
    {
        $order = Order::factory()->create(['customer_id' => null, 'status' => OrderStatus::Paid]);

        $this->service->notifyStatusChange($order);

        Notification::assertNothingSent();
    }

    public function test_a_registered_customer_with_no_email_is_silently_skipped(): void
    {
        $customer = Customer::factory()->create(['email' => null]);
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Paid]);

        $this->service->notifyStatusChange($order);

        Notification::assertNothingSent();
    }
}
