<?php

namespace Tests\Feature\Console;

use App\Domain\Enums\InventoryMovementType;
use App\Domain\Enums\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseExpiredReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_expired_orders_and_releases_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 3]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'reservation_expires_at' => now()->subMinute(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 3,
        ]);

        $this->artisan('orders:release-expired-reservations')->assertExitCode(0);

        $this->assertSame(0, $variant->fresh()->reserved_quantity);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);

        $history = $order->fresh()->statusHistory()->latest('id')->first();
        $this->assertSame('pending_payment', $history->from_status);
        $this->assertSame('cancelled', $history->to_status);

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'type' => InventoryMovementType::Release->value,
            'quantity_change' => 3,
        ]);
    }

    public function test_ignores_orders_that_have_not_expired_yet(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 2]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'reservation_expires_at' => now()->addMinutes(10),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 2,
        ]);

        $this->artisan('orders:release-expired-reservations')->assertExitCode(0);

        $this->assertSame(2, $variant->fresh()->reserved_quantity);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_ignores_orders_already_resolved(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Paid,
            'reservation_expires_at' => now()->subMinute(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 1,
        ]);

        $this->artisan('orders:release-expired-reservations')->assertExitCode(0);

        $this->assertSame(0, $variant->fresh()->reserved_quantity);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovementType::Release->value,
        ]);
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 2]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'reservation_expires_at' => now()->subMinute(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 2,
        ]);

        $this->artisan('orders:release-expired-reservations')->assertExitCode(0);
        $this->artisan('orders:release-expired-reservations')->assertExitCode(0);

        $this->assertSame(0, $variant->fresh()->reserved_quantity);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);

        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $order->id)
                ->where('type', InventoryMovementType::Release->value)
                ->count()
        );

        $this->assertSame(1, $order->fresh()->statusHistory()->count());
    }
}
