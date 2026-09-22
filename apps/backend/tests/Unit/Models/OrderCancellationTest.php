<?php

namespace Tests\Unit\Models;

use App\Domain\Enums\InventoryMovementType;
use App\Domain\Enums\OrderStatus;
use App\Domain\Exceptions\InvalidOrderTransition;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cancelling has to undo whatever hold the order still has on inventory, and
 * that depends entirely on how far the order got. These walk each starting
 * state through the real lifecycle first — no status is forced into place — so
 * the stock arithmetic under test is the arithmetic production produces.
 */
class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->owner()->create();
    }

    /**
     * An order that has reserved its stock: what checkout leaves behind.
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

    /**
     * The same order once an admin confirmed the payment, i.e. with the units
     * already out of stock for good.
     */
    private function paidOrder(int $quantity = 2, int $stock = 10): Order
    {
        $order = $this->reservedOrder($quantity, $stock);
        $order->markPaymentSubmitted();
        $order->confirmPayment($this->admin);

        return $order->fresh();
    }

    private function variant(): ProductVariant
    {
        return ProductVariant::query()->firstOrFail();
    }

    public function test_cancelling_an_unpaid_order_releases_the_reservation_and_leaves_stock_alone(): void
    {
        $order = $this->reservedOrder(quantity: 2, stock: 10);

        $order->cancel($this->admin, 'El cliente se arrepintio.');

        $variant = $this->variant();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        // Never sold, so `stock` was never touched — only the hold goes away.
        $this->assertSame(10, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
    }

    public function test_cancelling_an_unpaid_order_records_a_release_attributed_to_the_admin(): void
    {
        $order = $this->reservedOrder(quantity: 2);

        $order->cancel($this->admin, 'Pedido duplicado.');

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovementType::Release)
            ->firstOrFail();

        $this->assertSame(2, $movement->quantity_change);
        $this->assertSame('Pedido duplicado.', $movement->reason);
        $this->assertSame($order->id, $movement->order_id);
        $this->assertSame($this->admin->id, $movement->created_by);
    }

    public function test_cancelling_an_order_whose_proof_is_under_review_also_releases_it(): void
    {
        $order = $this->reservedOrder(quantity: 3, stock: 8);
        $order->markPaymentSubmitted();

        $order->cancel($this->admin, 'El comprobante era de otra tienda.');

        $variant = $this->variant();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(8, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
    }

    public function test_cancelling_a_paid_order_puts_the_units_back_on_the_shelf(): void
    {
        $order = $this->paidOrder(quantity: 2, stock: 10);

        // Confirming the payment took them out for good.
        $this->assertSame(8, $this->variant()->stock);

        $order->cancel($this->admin, 'El cliente pidio reembolso.');

        $variant = $this->variant();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
    }

    public function test_cancelling_a_paid_order_records_a_restock_not_a_release(): void
    {
        $order = $this->paidOrder(quantity: 2);

        $order->cancel($this->admin, 'Producto danado en almacen.');

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovementType::Restock)
            ->firstOrFail();

        $this->assertSame(2, $movement->quantity_change);
        $this->assertSame('Producto danado en almacen.', $movement->reason);
        $this->assertSame($this->admin->id, $movement->created_by);

        // A release would mean freeing a reservation that no longer exists;
        // the kardex has to tell the two apart.
        $this->assertFalse(
            InventoryMovement::query()->where('type', InventoryMovementType::Release)->exists()
        );
    }

    public function test_cancelling_an_order_being_prepared_also_puts_the_units_back(): void
    {
        $order = $this->paidOrder(quantity: 4, stock: 10);
        $order->advanceTo(OrderStatus::Preparing, $this->admin);

        $order->cancel($this->admin, 'Sin stock real en el deposito.');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $this->variant()->stock);
    }

    public function test_a_shipped_order_cannot_be_cancelled_and_its_stock_is_left_untouched(): void
    {
        $order = $this->paidOrder(quantity: 2, stock: 10);
        $order->advanceTo(OrderStatus::Preparing, $this->admin);
        $order->advanceTo(OrderStatus::Shipped, $this->admin);

        try {
            $order->cancel($this->admin, 'Ya no lo quiere.');
            $this->fail('Cancelling a shipped order should have been refused.');
        } catch (InvalidOrderTransition) {
            // Expected: the goods are already out the door.
        }

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertSame(8, $this->variant()->stock);
        $this->assertFalse(
            InventoryMovement::query()->where('type', InventoryMovementType::Restock)->exists()
        );
    }

    public function test_a_delivered_order_cannot_be_cancelled(): void
    {
        $order = $this->paidOrder();
        $order->advanceTo(OrderStatus::Preparing, $this->admin);
        $order->advanceTo(OrderStatus::Shipped, $this->admin);
        $order->advanceTo(OrderStatus::Delivered, $this->admin);

        $this->expectException(InvalidOrderTransition::class);

        $order->cancel($this->admin, 'Devolucion.');
    }

    public function test_cancelling_twice_releases_the_stock_only_once(): void
    {
        $order = $this->reservedOrder(quantity: 3, stock: 10);

        $order->cancel($this->admin, 'Duplicada.');
        $order->cancel($this->admin, 'Duplicada.');

        $this->assertSame(0, $this->variant()->reserved_quantity);
        $this->assertSame(10, $this->variant()->stock);
        $this->assertSame(
            1,
            InventoryMovement::query()->where('type', InventoryMovementType::Release)->count()
        );
        $this->assertSame(1, $order->statusHistory()->where('to_status', 'cancelled')->count());
    }

    public function test_cancelling_records_who_did_it_and_why(): void
    {
        $order = $this->reservedOrder();

        $order->cancel($this->admin, 'El cliente no responde.');

        $entry = $order->statusHistory()->latest('id')->firstOrFail();

        $this->assertSame('cancelled', $entry->to_status);
        $this->assertSame($this->admin->id, $entry->changed_by);
        $this->assertSame('El cliente no responde.', $entry->reason);
    }

    public function test_cancelling_clears_the_reservation_deadline(): void
    {
        $order = $this->reservedOrder();

        $this->assertNotNull($order->reservation_expires_at);

        $order->cancel($this->admin, 'Ya no la quiere.');

        $this->assertNull($order->fresh()->reservation_expires_at);
    }

    public function test_an_order_with_no_items_can_still_be_cancelled(): void
    {
        $order = Order::factory()->create(['reservation_expires_at' => now()->addMinutes(45)]);

        $order->cancel($this->admin, 'Orden vacia.');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }
}
