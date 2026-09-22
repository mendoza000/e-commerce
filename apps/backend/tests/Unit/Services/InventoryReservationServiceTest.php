<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryReservationService;
    }

    public function test_lock_variants_for_order_returns_locked_variants_keyed_by_id(): void
    {
        $variantA = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0, 'is_active' => true]);
        $variantB = ProductVariant::factory()->create(['stock' => 5, 'reserved_quantity' => 0, 'is_active' => true]);

        $locked = $this->service->lockVariantsForOrder([
            $variantA->id => 2,
            $variantB->id => 1,
        ]);

        $this->assertCount(2, $locked);
        $this->assertTrue($locked->get($variantA->id)->is($variantA));
        $this->assertTrue($locked->get($variantB->id)->is($variantB));
    }

    public function test_throws_validation_exception_for_nonexistent_variant(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->service->lockVariantsForOrder([999999 => 1]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.product_variant_id', $e->errors());

            throw $e;
        }
    }

    public function test_throws_validation_exception_for_inactive_variant(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0, 'is_active' => false]);

        try {
            $this->service->lockVariantsForOrder([$variant->id => 1]);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.product_variant_id', $e->errors());
        }
    }

    public function test_throws_validation_exception_for_insufficient_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5, 'reserved_quantity' => 3, 'is_active' => true]);

        try {
            $this->service->lockVariantsForOrder([$variant->id => 3]);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.quantity', $e->errors());
            $this->assertStringContainsString('Solo hay 2', $e->errors()['items.0.quantity'][0]);
        }
    }

    public function test_accumulates_errors_for_every_invalid_item_before_throwing(): void
    {
        $inactive = ProductVariant::factory()->create(['is_active' => false]);
        $insufficient = ProductVariant::factory()->create(['stock' => 1, 'reserved_quantity' => 0, 'is_active' => true]);

        try {
            $this->service->lockVariantsForOrder([
                $inactive->id => 1,
                $insufficient->id => 5,
            ]);
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.product_variant_id', $e->errors());
            $this->assertArrayHasKey('items.1.quantity', $e->errors());
        }
    }

    public function test_reserve_increments_reserved_quantity_and_records_negative_movement(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);
        $order = Order::factory()->create();

        $locked = $this->service->lockVariantsForOrder([$variant->id => 4]);
        $this->service->reserve($order, $locked, [$variant->id => 4]);

        $this->assertSame(4, $variant->fresh()->reserved_quantity);

        $movement = InventoryMovement::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame(InventoryMovementType::Reservation, $movement->type);
        $this->assertSame(-4, $movement->quantity_change);
        $this->assertSame($order->id, $movement->order_id);
    }

    public function test_release_decrements_reserved_quantity_and_records_positive_movement(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 10,
            'reserved_quantity' => 4,
        ]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 4,
        ]);

        $this->service->release($order, 'Reservation expired.');

        $this->assertSame(0, $variant->fresh()->reserved_quantity);

        $movement = InventoryMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('type', InventoryMovementType::Release)
            ->firstOrFail();

        $this->assertSame(4, $movement->quantity_change);
        $this->assertSame('Reservation expired.', $movement->reason);
    }

    public function test_release_clamps_reserved_quantity_at_zero(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 1]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 5,
        ]);

        $this->service->release($order, 'Reservation expired.');

        $this->assertSame(0, $variant->fresh()->reserved_quantity);
    }

    public function test_commit_turns_the_reservation_into_a_definitive_stock_deduction(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 4]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 4,
        ]);

        $this->service->commit($order);

        $variant->refresh();

        $this->assertSame(6, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
    }

    public function test_commit_records_a_sale_movement_attributed_to_the_admin(): void
    {
        $admin = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 3]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 3,
        ]);

        $this->service->commit($order, $admin);

        $movement = InventoryMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('type', InventoryMovementType::Sale)
            ->firstOrFail();

        $this->assertSame(-3, $movement->quantity_change);
        $this->assertSame($admin->id, $movement->created_by);
        $this->assertSame($order->id, $movement->order_id);
    }

    public function test_commit_clamps_both_counters_at_zero(): void
    {
        // Stock adjusted out of band between reservation and confirmation.
        $variant = ProductVariant::factory()->create(['stock' => 1, 'reserved_quantity' => 1]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 5,
        ]);

        $this->service->commit($order);

        $variant->refresh();

        $this->assertSame(0, $variant->stock);
        $this->assertSame(0, $variant->reserved_quantity);
    }

    public function test_restock_puts_the_units_back_and_leaves_reservations_alone(): void
    {
        // What a paid order looks like: committed, so nothing is reserved.
        $variant = ProductVariant::factory()->create(['stock' => 6, 'reserved_quantity' => 2]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 4,
        ]);

        $this->service->restock($order, 'Orden cancelada.');

        $variant->refresh();

        $this->assertSame(10, $variant->stock);
        // The 2 reserved units belong to somebody else's order.
        $this->assertSame(2, $variant->reserved_quantity);
    }

    public function test_restock_records_the_movement_attributed_to_the_admin(): void
    {
        $admin = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 6, 'reserved_quantity' => 0]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 4,
        ]);

        $this->service->restock($order, 'Orden cancelada.', $admin);

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovementType::Restock)
            ->firstOrFail();

        $this->assertSame(4, $movement->quantity_change);
        $this->assertSame('Orden cancelada.', $movement->reason);
        $this->assertSame($order->id, $movement->order_id);
        $this->assertSame($admin->id, $movement->created_by);
    }

    public function test_release_can_be_attributed_to_the_admin_who_cancelled(): void
    {
        $admin = User::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 5]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'quantity' => 5,
        ]);

        $this->service->release($order, 'Cancelada por el administrador.', $admin);

        $movement = InventoryMovement::query()
            ->where('type', InventoryMovementType::Release)
            ->firstOrFail();

        $this->assertSame($admin->id, $movement->created_by);
    }
}
