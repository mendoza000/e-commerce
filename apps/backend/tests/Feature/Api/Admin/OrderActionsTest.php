<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Enums\InventoryMovementType;
use App\Domain\Enums\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The operational cycle the panel has to be able to drive end to end: charge,
 * prepare, ship — and cancel, from wherever the order happens to be.
 */
class OrderActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();

        $this->admin = User::factory()->owner()->create();
    }

    /**
     * An order with stock reserved, as checkout leaves it.
     */
    private function order(int $quantity = 2, int $stock = 10): Order
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

    private function submittedOrder(int $quantity = 2, int $stock = 10): Order
    {
        $order = $this->order($quantity, $stock);
        $order->markPaymentSubmitted();

        return $order->fresh();
    }

    public function test_confirming_a_payment_requires_a_session(): void
    {
        $order = $this->submittedOrder();

        $this->postJson("/api/admin/orders/{$order->order_number}/confirm-payment")
            ->assertUnauthorized();
    }

    public function test_staff_can_confirm_a_payment(): void
    {
        $order = $this->submittedOrder(quantity: 2, stock: 10);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->postJson("/api/admin/orders/{$order->order_number}/confirm-payment")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.actions.available_transitions.0.value', 'preparing');

        $variant = ProductVariant::query()->firstOrFail();

        $this->assertSame(8, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
        $this->assertSame(
            $staff->id,
            $order->statusHistory()->where('to_status', 'paid')->firstOrFail()->changed_by
        );
        $this->assertSame(
            $staff->id,
            InventoryMovement::query()->where('type', InventoryMovementType::Sale)->firstOrFail()->created_by
        );
    }

    public function test_confirming_an_order_that_has_no_proof_yet_is_refused(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/confirm-payment")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_order_transition');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_confirming_twice_deducts_the_stock_once(): void
    {
        $order = $this->submittedOrder(quantity: 2, stock: 10);
        $url = "/api/admin/orders/{$order->order_number}/confirm-payment";

        $this->actingAs($this->admin)->postJson($url)->assertOk();
        $this->actingAs($this->admin)->postJson($url)->assertOk();

        $this->assertSame(8, ProductVariant::query()->firstOrFail()->stock);
        $this->assertSame(
            1,
            InventoryMovement::query()->where('type', InventoryMovementType::Sale)->count()
        );
    }

    public function test_rejecting_a_payment_needs_a_reason(): void
    {
        $order = $this->submittedOrder();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/reject-payment", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonStructure(['error' => ['fields' => ['reason']]]);

        $this->assertSame(OrderStatus::PaymentSubmitted, $order->fresh()->status);
    }

    public function test_rejecting_a_payment_sends_the_order_back_to_pending_with_the_reason(): void
    {
        $order = $this->submittedOrder();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/reject-payment", [
                'reason' => 'El monto no coincide con la orden.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_payment');

        $entry = $order->statusHistory()->latest('id')->firstOrFail();

        $this->assertSame('pending_payment', $entry->to_status);
        $this->assertSame('El monto no coincide con la orden.', $entry->reason);
        $this->assertSame($this->admin->id, $entry->changed_by);
        // The customer gets another window to pay in, not a dead order.
        $this->assertNotNull($order->fresh()->reservation_expires_at);
    }

    public function test_a_paid_order_cannot_have_its_payment_rejected(): void
    {
        $order = $this->submittedOrder();
        $order->confirmPayment($this->admin);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/reject-payment", [
                'reason' => 'Me equivoque al confirmar.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_order_transition');
    }

    public function test_a_paid_order_can_be_walked_to_delivered(): void
    {
        $order = $this->submittedOrder();
        $order->confirmPayment($this->admin);

        $url = "/api/admin/orders/{$order->order_number}/transition";

        foreach (['preparing', 'shipped', 'delivered'] as $status) {
            $this->actingAs($this->admin)
                ->postJson($url, ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);

        // Every move is attributed, none of them silently.
        $moves = $order->statusHistory()->whereIn('to_status', ['preparing', 'shipped', 'delivered'])->get();

        $this->assertCount(3, $moves);
        $this->assertTrue($moves->every(fn ($entry) => $entry->changed_by === $this->admin->id));
    }

    public function test_a_transition_carries_an_optional_note_into_the_history(): void
    {
        $order = $this->submittedOrder();
        $order->confirmPayment($this->admin);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/transition", [
                'status' => 'preparing',
                'reason' => 'Empacado por el turno de la tarde.',
            ])
            ->assertOk();

        $this->assertSame(
            'Empacado por el turno de la tarde.',
            $order->statusHistory()->latest('id')->firstOrFail()->reason
        );
    }

    /**
     * The transition endpoint must not become a back door around the actions
     * that move money or stock: paid, pending_payment and cancelled each have
     * their own endpoint, and skipping it would skip the side effect.
     */
    public function test_the_transition_endpoint_refuses_the_statuses_that_have_side_effects(): void
    {
        $order = $this->submittedOrder();

        foreach (['paid', 'cancelled', 'pending_payment'] as $status) {
            $this->actingAs($this->admin)
                ->postJson("/api/admin/orders/{$order->order_number}/transition", ['status' => $status])
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_error');
        }

        $this->assertSame(OrderStatus::PaymentSubmitted, $order->fresh()->status);
    }

    public function test_an_unpaid_order_cannot_jump_straight_to_shipped(): void
    {
        $order = $this->submittedOrder();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/transition", ['status' => 'shipped'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_order_transition');
    }

    public function test_cancelling_needs_a_reason(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/cancel", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_cancelling_an_unpaid_order_frees_the_reservation(): void
    {
        $order = $this->order(quantity: 3, stock: 10);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/cancel", [
                'reason' => 'El cliente no responde.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.actions.can_cancel', false);

        $variant = ProductVariant::query()->firstOrFail();

        $this->assertSame(10, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
    }

    public function test_cancelling_a_paid_order_returns_the_stock(): void
    {
        $order = $this->submittedOrder(quantity: 2, stock: 10);
        $order->confirmPayment($this->admin);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/cancel", [
                'reason' => 'Reembolso acordado con el cliente.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(10, ProductVariant::query()->firstOrFail()->stock);

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovementType::Restock)
            ->firstOrFail();

        $this->assertSame($this->admin->id, $movement->created_by);
        $this->assertSame($order->id, $movement->order_id);
    }

    public function test_a_shipped_order_cannot_be_cancelled_from_the_panel(): void
    {
        $order = $this->submittedOrder(quantity: 2, stock: 10);
        $order->confirmPayment($this->admin);
        $order->advanceTo(OrderStatus::Preparing, $this->admin);
        $order->advanceTo(OrderStatus::Shipped, $this->admin);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->order_number}/cancel", [
                'reason' => 'Se arrepintio.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_order_transition');

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertSame(8, ProductVariant::query()->firstOrFail()->stock);
    }

    public function test_staff_can_cancel_too(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->staff()->create())
            ->postJson("/api/admin/orders/{$order->order_number}/cancel", [
                'reason' => 'Pedido de prueba.',
            ])
            ->assertOk();
    }
}
