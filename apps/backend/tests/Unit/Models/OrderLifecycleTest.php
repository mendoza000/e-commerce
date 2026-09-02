<?php

namespace Tests\Unit\Models;

use App\Domain\Enums\InventoryMovementType;
use App\Domain\Enums\OrderStatus;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates an order that has already reserved stock, i.e. the state
     * OrderService leaves behind at the end of checkout.
     */
    private function reservedOrder(int $quantity = 2, int $stock = 10): Order
    {
        $variant = ProductVariant::factory()->create([
            'stock' => $stock,
            'reserved_quantity' => $quantity,
        ]);

        $order = Order::factory()->create([
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'reservation_expires_at' => now()->addMinutes(45),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => $quantity,
        ]);

        return $order;
    }

    public function test_transition_to_records_the_change_in_the_history(): void
    {
        $admin = User::factory()->create();
        $order = Order::factory()->create();

        $order->transitionTo(OrderStatus::PaymentSubmitted, $admin, 'Comprobante recibido.');

        $this->assertSame(OrderStatus::PaymentSubmitted, $order->fresh()->status);

        $entry = $order->statusHistory()->firstOrFail();

        $this->assertSame('pending_payment', $entry->from_status);
        $this->assertSame('payment_submitted', $entry->to_status);
        $this->assertSame($admin->id, $entry->changed_by);
        $this->assertSame('Comprobante recibido.', $entry->reason);
    }

    public function test_transition_to_rejects_a_move_the_business_does_not_allow(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

        $this->expectException(InvalidOrderTransition::class);

        $order->transitionTo(OrderStatus::Shipped);
    }

    public function test_a_rejected_transition_leaves_no_trace(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        try {
            $order->transitionTo(OrderStatus::Cancelled);
        } catch (InvalidOrderTransition) {
            // Expected.
        }

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(0, $order->statusHistory()->count());
    }

    public function test_marking_payment_submitted_extends_the_reservation_for_review(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');
        config(['commerce.payment_review_minutes' => 4320]);

        $order = $this->reservedOrder();

        $order->markPaymentSubmitted();

        $this->assertSame(OrderStatus::PaymentSubmitted, $order->fresh()->status);
        $this->assertSame(
            '2026-09-03 10:00:00',
            $order->fresh()->reservation_expires_at->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    public function test_re_uploading_a_proof_only_refreshes_the_review_window(): void
    {
        $order = $this->reservedOrder();
        $order->markPaymentSubmitted();

        $order->markPaymentSubmitted();

        $this->assertSame(OrderStatus::PaymentSubmitted, $order->fresh()->status);
        // One entry, not two: the status never actually changed the second time.
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_confirming_payment_commits_the_reservation_as_a_sale(): void
    {
        $admin = User::factory()->create();
        $order = $this->reservedOrder(quantity: 2, stock: 10);
        $order->markPaymentSubmitted();

        $order->confirmPayment($admin);

        $variant = ProductVariant::query()->firstOrFail();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(8, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
        $this->assertNull($order->fresh()->reservation_expires_at);
        $this->assertTrue(
            InventoryMovement::query()->where('type', InventoryMovementType::Sale)->exists()
        );
    }

    public function test_confirming_payment_twice_deducts_stock_only_once(): void
    {
        $admin = User::factory()->create();
        $order = $this->reservedOrder(quantity: 2, stock: 10);
        $order->markPaymentSubmitted();

        $order->confirmPayment($admin);
        $order->confirmPayment($admin);

        $this->assertSame(8, ProductVariant::query()->firstOrFail()->stock);
        $this->assertSame(
            1,
            InventoryMovement::query()->where('type', InventoryMovementType::Sale)->count()
        );
    }

    public function test_rejecting_a_payment_gives_the_customer_a_fresh_reservation_window(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');
        config(['commerce.reservation_minutes' => 45]);

        $admin = User::factory()->create();
        $order = $this->reservedOrder();
        $order->markPaymentSubmitted();

        $order->rejectPayment($admin, 'El comprobante no corresponde al monto.');

        $order->refresh();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('2026-08-31 10:45:00', $order->reservation_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(
            'El comprobante no corresponde al monto.',
            $order->statusHistory()->latest('id')->firstOrFail()->reason
        );

        Carbon::setTestNow();
    }

    public function test_cancel_expired_reservation_releases_stock_and_cancels(): void
    {
        $order = $this->reservedOrder(quantity: 3);
        $order->update(['reservation_expires_at' => now()->subMinute()]);

        $this->assertTrue($order->cancelExpiredReservation('Expiró.'));

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, ProductVariant::query()->firstOrFail()->reserved_quantity);
    }

    public function test_cancel_expired_reservation_is_a_no_op_when_the_deadline_has_not_passed(): void
    {
        $order = $this->reservedOrder(quantity: 3);

        $this->assertFalse($order->cancelExpiredReservation('Expiró.'));

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(3, ProductVariant::query()->firstOrFail()->reserved_quantity);
    }

    public function test_a_paid_order_can_no_longer_accept_a_proof(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->assertFalse($order->canAcceptPaymentProof());
        $this->assertTrue(Order::factory()->create()->canAcceptPaymentProof());
    }

    public function test_access_is_granted_to_the_owning_customer_or_the_right_document_number(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'document_number' => '12345678',
        ]);

        $this->assertTrue($order->isAccessibleBy($customer, null));
        $this->assertTrue($order->isAccessibleBy(null, '12345678'));
        $this->assertFalse($order->isAccessibleBy($other, null));
        $this->assertFalse($order->isAccessibleBy(null, '87654321'));
        $this->assertFalse($order->isAccessibleBy(null, null));
    }

    public function test_advancing_a_paid_order_records_who_moved_it(): void
    {
        $admin = User::factory()->create();
        $order = $this->reservedOrder();
        $order->markPaymentSubmitted();
        $order->confirmPayment($admin);

        $order->advanceTo(OrderStatus::Preparing, $admin, 'Empacada.');

        $entry = $order->statusHistory()->latest('id')->firstOrFail();

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
        $this->assertSame('preparing', $entry->to_status);
        $this->assertSame($admin->id, $entry->changed_by);
        $this->assertSame('Empacada.', $entry->reason);
    }

    public function test_advancing_to_the_status_it_already_has_is_a_no_op(): void
    {
        $admin = User::factory()->create();
        $order = $this->reservedOrder();
        $order->markPaymentSubmitted();
        $order->confirmPayment($admin);

        $order->advanceTo(OrderStatus::Preparing, $admin);
        $order->advanceTo(OrderStatus::Preparing, $admin);

        $this->assertSame(
            1,
            $order->statusHistory()->where('to_status', 'preparing')->count()
        );
    }

    public function test_marking_shipped_stores_the_courier_tracking_and_note(): void
    {
        $admin = User::factory()->create();
        $order = $this->reservedOrder();
        $order->markPaymentSubmitted();
        $order->confirmPayment($admin);
        $order->advanceTo(OrderStatus::Preparing, $admin);

        $order->advanceTo(OrderStatus::Shipped, $admin, shipping: [
            'courier' => 'MRW',
            'tracking_code' => 'ABC123',
            'shipping_note' => 'Dejar en portería.',
        ]);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Shipped, $fresh->status);
        $this->assertSame('MRW', $fresh->courier);
        $this->assertSame('ABC123', $fresh->tracking_code);
        $this->assertSame('Dejar en portería.', $fresh->shipping_note);
    }

    public function test_advancing_without_shipping_details_leaves_them_null(): void
    {
        $admin = User::factory()->create();
        $order = $this->reservedOrder();
        $order->markPaymentSubmitted();
        $order->confirmPayment($admin);
        $order->advanceTo(OrderStatus::Preparing, $admin);

        $order->advanceTo(OrderStatus::Shipped, $admin);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Shipped, $fresh->status);
        $this->assertNull($fresh->courier);
        $this->assertNull($fresh->tracking_code);
    }
}
