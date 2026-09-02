<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    // -----------------------------------------------------------------
    // Adjusting
    // -----------------------------------------------------------------

    public function test_an_owner_adds_units_with_a_reason(): void
    {
        $owner = $this->owner();
        $variant = ProductVariant::factory()->create(['stock' => 4, 'reserved_quantity' => 0]);

        $this->actingAs($owner)
            ->postJson("/api/admin/variants/{$variant->id}/adjust-stock", [
                'quantity_change' => 12,
                'reason' => 'Llegó reposición del proveedor.',
            ])
            ->assertOk()
            ->assertJsonPath('data.stock', 16)
            ->assertJsonPath('data.available_stock', 16);

        $movement = InventoryMovement::query()->sole();

        $this->assertSame(InventoryMovementType::Adjustment, $movement->type);
        $this->assertSame(12, $movement->quantity_change);
        $this->assertSame($owner->id, $movement->created_by);
    }

    public function test_an_owner_writes_off_damaged_units(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/variants/{$variant->id}/adjust-stock", [
                'quantity_change' => -3,
                'reason' => 'Tres unidades dañadas en bodega.',
            ])
            ->assertOk()
            ->assertJsonPath('data.stock', 7);
    }

    public function test_the_reason_is_mandatory(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/variants/{$variant->id}/adjust-stock", ['quantity_change' => 5])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.reason.0', 'Debes indicar el motivo del ajuste.');

        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_an_adjustment_of_zero_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/variants/{$variant->id}/adjust-stock", [
                'quantity_change' => 0,
                'reason' => 'Nada.',
            ])
            ->assertStatus(422);
    }

    /**
     * The invariant this endpoint exists to protect: those units are already
     * promised to open orders, and pushing stock under them is how a store
     * oversells against live reservations.
     */
    public function test_stock_cannot_be_taken_below_what_open_orders_reserved(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 6]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/variants/{$variant->id}/adjust-stock", [
                'quantity_change' => -5,
                'reason' => 'Conteo físico.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonStructure(['error' => ['fields' => ['quantity_change']]]);

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_stock_can_be_taken_down_to_exactly_the_reserved_quantity(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 6]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/variants/{$variant->id}/adjust-stock", [
                'quantity_change' => -4,
                'reason' => 'Conteo físico.',
            ])
            ->assertOk()
            ->assertJsonPath('data.stock', 6)
            ->assertJsonPath('data.available_stock', 0);
    }

    public function test_staff_cannot_adjust_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $this->actingAs(User::factory()->staff()->create())
            ->postJson("/api/admin/variants/{$variant->id}/adjust-stock", [
                'quantity_change' => 5,
                'reason' => 'Inventando stock.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->assertSame(10, $variant->fresh()->stock);
    }

    // -----------------------------------------------------------------
    // The kardex
    // -----------------------------------------------------------------

    public function test_it_lists_the_movements_of_a_variant_newest_first(): void
    {
        $variant = ProductVariant::factory()->create();

        InventoryMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'type' => InventoryMovementType::Sale,
            'created_at' => now()->subDay(),
        ]);
        InventoryMovement::factory()->adjustment()->create([
            'product_variant_id' => $variant->id,
            'reason' => 'Reposición.',
            'created_at' => now(),
        ]);

        $this->actingAs($this->owner())
            ->getJson("/api/admin/variants/{$variant->id}/movements")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'adjustment')
            ->assertJsonPath('data.0.type_label', 'Ajuste manual')
            ->assertJsonPath('data.0.reason', 'Reposición.')
            ->assertJsonPath('data.1.type', 'sale');
    }

    public function test_the_kardex_only_shows_the_movements_of_that_variant(): void
    {
        $variant = ProductVariant::factory()->create();
        InventoryMovement::factory()->create(['product_variant_id' => $variant->id]);
        InventoryMovement::factory()->create();

        $this->actingAs($this->owner())
            ->getJson("/api/admin/variants/{$variant->id}/movements")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_kardex_filters_by_movement_type(): void
    {
        $variant = ProductVariant::factory()->create();
        InventoryMovement::factory()->create(['product_variant_id' => $variant->id]);
        InventoryMovement::factory()->adjustment()->create(['product_variant_id' => $variant->id]);

        $this->actingAs($this->owner())
            ->getJson("/api/admin/variants/{$variant->id}/movements?type=adjustment")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'adjustment');
    }

    public function test_an_unknown_movement_type_is_a_validation_error(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->actingAs($this->owner())
            ->getJson("/api/admin/variants/{$variant->id}/movements?type=inventado")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_the_kardex_is_paginated(): void
    {
        $variant = ProductVariant::factory()->create();
        InventoryMovement::factory()->count(5)->create(['product_variant_id' => $variant->id]);

        $this->actingAs($this->owner())
            ->getJson("/api/admin/variants/{$variant->id}/movements?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5);
    }

    public function test_a_movement_names_the_order_and_the_admin_behind_it(): void
    {
        $admin = $this->owner();
        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create();

        InventoryMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/variants/{$variant->id}/movements")
            ->assertOk()
            ->assertJsonPath('data.0.order_number', $order->order_number)
            ->assertJsonPath('data.0.created_by.name', $admin->name);
    }

    /**
     * A movement nobody decided — a customer's reservation, the scheduled
     * sweep releasing an expired one — comes back with a null author rather
     * than a made-up one.
     */
    public function test_a_movement_the_system_made_has_no_author(): void
    {
        $variant = ProductVariant::factory()->create();
        InventoryMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'created_by' => null,
        ]);

        $this->actingAs($this->owner())
            ->getJson("/api/admin/variants/{$variant->id}/movements")
            ->assertOk()
            ->assertJsonPath('data.0.created_by', null);
    }

    public function test_staff_may_read_the_kardex(): void
    {
        $variant = ProductVariant::factory()->create();
        InventoryMovement::factory()->create(['product_variant_id' => $variant->id]);

        $this->actingAs(User::factory()->staff()->create())
            ->getJson("/api/admin/variants/{$variant->id}/movements")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
