<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Enums\FulfillmentMethodType;
use App\Models\Currency;
use App\Models\FulfillmentMethod;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentMethodManagementTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private Currency $ves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();

        $this->usd = Currency::factory()->create(['code' => 'USD']);
        $this->ves = Currency::factory()->create(['code' => 'VES']);

        StoreSetting::factory()->accepting([$this->usd, $this->ves])->create();
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    // -----------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------

    public function test_it_lists_the_methods_in_the_order_the_storefront_shows_them(): void
    {
        FulfillmentMethod::factory()->courierManual()->create(['currency_id' => $this->usd->id, 'position' => 1]);
        FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id, 'position' => 0]);

        $this->actingAs($this->owner())
            ->getJson('/api/admin/fulfillment-methods')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'delivery_propio')
            ->assertJsonPath('data.1.type', 'courier_manual')
            ->assertJsonPath('data.0.orders_count', 0);
    }

    public function test_it_publishes_the_types_this_installation_knows(): void
    {
        $response = $this->actingAs($this->owner())
            ->getJson('/api/admin/fulfillment-method-types')
            ->assertOk()
            ->assertJsonCount(count(FulfillmentMethodType::cases()), 'data');

        $types = collect($response->json('data'))->keyBy('value');

        $this->assertSame('Retiro en tienda', $types['retiro_en_tienda']['label']);
    }

    // -----------------------------------------------------------------
    // Creating and editing
    // -----------------------------------------------------------------

    public function test_it_creates_a_method_and_appends_it_to_the_checkout_list(): void
    {
        FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id, 'position' => 0]);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/fulfillment-methods', [
                'type' => FulfillmentMethodType::CourierManual->value,
                'label' => 'Courier nacional',
                'requires_tracking_code' => true,
                'base_cost' => 5,
                'currency_id' => $this->usd->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'courier_manual')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.requires_tracking_code', true)
            ->assertJsonPath('data.base_cost', '5.000000');
    }

    public function test_pickup_in_store_can_be_created_with_no_cost_or_currency(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/fulfillment-methods', [
                'type' => FulfillmentMethodType::RetiroEnTienda->value,
                'label' => 'Retiro en tienda',
            ])
            ->assertCreated()
            ->assertJsonPath('data.base_cost', null)
            ->assertJsonPath('data.currency', null);
    }

    public function test_a_base_cost_without_a_currency_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->postJson('/api/admin/fulfillment-methods', [
                'type' => FulfillmentMethodType::DeliveryPropio->value,
                'label' => 'Delivery propio',
                'base_cost' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['currency_id']]]);
    }

    /**
     * Same inconsistency PaymentMethodStoreRequest refuses from its own side.
     */
    public function test_a_method_cannot_price_in_a_currency_the_store_does_not_accept(): void
    {
        $cop = Currency::factory()->create(['code' => 'COP']);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/fulfillment-methods', [
                'type' => FulfillmentMethodType::DeliveryPropio->value,
                'label' => 'Delivery en pesos',
                'base_cost' => 5,
                'currency_id' => $cop->id,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['currency_id']]]);

        $this->assertSame(0, FulfillmentMethod::query()->count());
    }

    public function test_editing_base_cost_alone_does_not_misread_the_stored_currency_as_missing(): void
    {
        $method = FulfillmentMethod::factory()->create(['currency_id' => $this->usd->id, 'base_cost' => 5]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/fulfillment-methods/{$method->id}", ['base_cost' => 8])
            ->assertOk()
            ->assertJsonPath('data.base_cost', '8.000000');
    }

    public function test_a_method_is_deactivated_without_being_deleted(): void
    {
        $method = FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/fulfillment-methods/{$method->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('fulfillment_methods', ['id' => $method->id]);
    }

    public function test_the_type_of_a_method_cannot_be_changed(): void
    {
        $method = FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/fulfillment-methods/{$method->id}", [
                'type' => FulfillmentMethodType::CourierManual->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'error.fields.type.0',
                'El tipo de un método de envío no se cambia: crea otro método y desactiva este.',
            );

        $this->assertSame(FulfillmentMethodType::DeliveryPropio, $method->fresh()->type);
    }

    // -----------------------------------------------------------------
    // Deleting and ordering
    // -----------------------------------------------------------------

    public function test_an_unused_method_is_deleted(): void
    {
        $method = FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/fulfillment-methods/{$method->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('fulfillment_methods', ['id' => $method->id]);
    }

    public function test_a_method_that_was_used_is_not_deleted(): void
    {
        $method = FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id]);
        Order::factory()->create(['fulfillment_method_id' => $method->id]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/fulfillment-methods/{$method->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');

        $this->assertDatabaseHas('fulfillment_methods', ['id' => $method->id]);
    }

    public function test_it_reorders_the_checkout_list(): void
    {
        $first = FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id, 'position' => 0]);
        $second = FulfillmentMethod::factory()->courierManual()->create(['currency_id' => $this->usd->id, 'position' => 1]);

        $this->actingAs($this->owner())
            ->postJson('/api/admin/fulfillment-methods/reorder', [
                'fulfillment_methods' => [$second->id, $first->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    // -----------------------------------------------------------------
    // The storefront sees the result
    // -----------------------------------------------------------------

    public function test_deactivating_a_method_takes_it_off_the_storefront(): void
    {
        $method = FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->getJson('/api/fulfillment-methods')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/fulfillment-methods/{$method->id}", ['is_active' => false])
            ->assertOk();

        $this->getJson('/api/fulfillment-methods')->assertOk()->assertJsonCount(0, 'data');
    }

    // -----------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_read_or_write_shipping_configuration(): void
    {
        $staff = User::factory()->staff()->create();
        $method = FulfillmentMethod::factory()->create(['currency_id' => $this->ves->id]);

        $this->actingAs($staff)->getJson('/api/admin/fulfillment-methods')->assertStatus(403);
        $this->actingAs($staff)->getJson('/api/admin/fulfillment-method-types')->assertStatus(403);
        $this->actingAs($staff)->getJson("/api/admin/fulfillment-methods/{$method->id}")->assertStatus(403);

        $this->actingAs($staff)
            ->patchJson("/api/admin/fulfillment-methods/{$method->id}", ['label' => 'Mi método'])
            ->assertStatus(403);

        $this->actingAs($staff)->deleteJson("/api/admin/fulfillment-methods/{$method->id}")->assertStatus(403);

        $this->assertSame('Delivery propio', $method->fresh()->label);
    }
}
