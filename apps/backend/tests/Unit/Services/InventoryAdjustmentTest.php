<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * InventoryReservationService::adjust() — the manual stock correction.
 *
 * Kept apart from InventoryReservationServiceTest because everything in that
 * file is driven by an order, and this is the one movement a human causes on
 * their own. What it has to protect is the invariant nothing else in the
 * service can break: stock may never fall below what open orders have already
 * reserved.
 */
class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReservationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryReservationService;
    }

    public function test_it_adds_units_and_records_an_adjustment_movement(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 4, 'reserved_quantity' => 0]);

        $adjusted = $this->service->adjust($variant, 12, 'Llegó reposición del proveedor.');

        $this->assertSame(16, $adjusted->stock);
        $this->assertSame(16, $variant->fresh()->stock);

        $movement = InventoryMovement::query()->sole();

        $this->assertSame(InventoryMovementType::Adjustment, $movement->type);
        $this->assertSame(12, $movement->quantity_change);
        $this->assertSame('Llegó reposición del proveedor.', $movement->reason);
        $this->assertSame($variant->sku, $movement->sku);
        // No order caused this: it is a human correcting the shelf.
        $this->assertNull($movement->order_id);
    }

    public function test_it_subtracts_units_for_damage_or_a_recount(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);

        $this->service->adjust($variant, -3, 'Tres unidades rotas en bodega.');

        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame(-3, InventoryMovement::query()->sole()->quantity_change);
    }

    public function test_it_records_the_admin_who_made_the_adjustment(): void
    {
        $admin = User::factory()->owner()->create();
        $variant = ProductVariant::factory()->create(['stock' => 0, 'reserved_quantity' => 0]);

        $this->service->adjust($variant, 5, 'Conteo físico.', $admin);

        $this->assertSame($admin->id, InventoryMovement::query()->sole()->created_by);
    }

    public function test_it_refuses_to_leave_stock_below_what_open_orders_reserved(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 6]);

        try {
            $this->service->adjust($variant, -5, 'Conteo físico.');
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity_change', $e->errors());
            $this->assertStringContainsString('6 unidades', $e->errors()['quantity_change'][0]);
        }

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_it_allows_taking_stock_down_to_exactly_the_reserved_quantity(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 6]);

        $this->service->adjust($variant, -4, 'Conteo físico.');

        $this->assertSame(6, $variant->fresh()->stock);
    }

    public function test_it_refuses_to_push_stock_negative(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 2, 'reserved_quantity' => 0]);

        try {
            $this->service->adjust($variant, -5, 'Conteo físico.');
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('solo hay 2 unidades', $e->errors()['quantity_change'][0]);
        }

        $this->assertSame(2, $variant->fresh()->stock);
    }

    /**
     * The instance handed in can be stale — the panel loaded it before a sale
     * was confirmed. The service re-reads the row under a lock, so the delta
     * applies to the stock as it is now, not as the caller last saw it.
     */
    public function test_the_delta_applies_to_the_current_row_not_to_a_stale_instance(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);

        $stale = ProductVariant::query()->findOrFail($variant->id);
        ProductVariant::query()->whereKey($variant->id)->update(['stock' => 4]);

        $this->service->adjust($stale, 1, 'Una unidad devuelta.');

        $this->assertSame(5, $variant->fresh()->stock);
    }
}
