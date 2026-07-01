<?php

namespace Tests\Unit\Models;

use App\Domain\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_movement_resolves_variant_and_order(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create();

        $movement = InventoryMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
        ]);

        $this->assertSame(InventoryMovementType::Sale, $movement->type);
        $this->assertTrue($movement->variant->is($variant));
        $this->assertTrue($movement->order->is($order));
    }

    public function test_release_movement_resolves_variant_and_order(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create();

        $movement = InventoryMovement::factory()->release()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
        ]);

        $this->assertSame(InventoryMovementType::Release, $movement->type);
        $this->assertTrue($movement->variant->is($variant));
        $this->assertTrue($movement->order->is($order));
    }

    public function test_adjustment_movement_can_be_created_without_order(): void
    {
        $variant = ProductVariant::factory()->create();

        $movement = InventoryMovement::factory()->adjustment()->create([
            'product_variant_id' => $variant->id,
            'order_id' => null,
        ]);

        $this->assertSame(InventoryMovementType::Adjustment, $movement->type);
        $this->assertTrue($movement->variant->is($variant));
        $this->assertNull($movement->order_id);
        $this->assertNull($movement->order);
    }
}
