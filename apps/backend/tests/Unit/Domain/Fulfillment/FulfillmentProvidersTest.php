<?php

namespace Tests\Unit\Domain\Fulfillment;

use App\Domain\Enums\FulfillmentMethodType;
use App\Domain\Fulfillment\FulfillmentProviderRegistry;
use App\Domain\Fulfillment\Providers\CourierManualProvider;
use App\Domain\Fulfillment\Providers\DeliveryPropioProvider;
use App\Domain\Fulfillment\Providers\RetiroEnTiendaProvider;
use App\Models\FulfillmentMethod;
use App\Models\FulfillmentZoneRate;
use App\Models\Municipality;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentProvidersTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_resolves_the_provider_class_for_each_type(): void
    {
        $registry = app(FulfillmentProviderRegistry::class);

        $this->assertInstanceOf(
            DeliveryPropioProvider::class,
            $registry->for(FulfillmentMethod::factory()->create())
        );
        $this->assertInstanceOf(
            RetiroEnTiendaProvider::class,
            $registry->for(FulfillmentMethod::factory()->retiroEnTienda()->create())
        );
        $this->assertInstanceOf(
            CourierManualProvider::class,
            $registry->for(FulfillmentMethod::factory()->courierManual()->create())
        );
    }

    public function test_pickup_in_store_always_costs_nothing_regardless_of_zone_rates(): void
    {
        $method = FulfillmentMethod::factory()->retiroEnTienda()->create(['base_cost' => 15]);
        $state = State::factory()->create();
        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $method->id,
            'state_id' => $state->id,
            'cost' => 999,
        ]);

        $this->assertSame('0.000000', $method->estimateCostFor($state, null));
        $this->assertSame('0.000000', $method->estimateCostFor(null, null));
    }

    public function test_pickup_in_store_never_requires_a_tracking_code(): void
    {
        $method = FulfillmentMethod::factory()->retiroEnTienda()->create(['requires_tracking_code' => true]);

        $this->assertFalse($method->provider()->requiresTrackingCode());
    }

    public function test_cost_falls_back_to_the_base_cost_when_no_zone_rate_is_configured(): void
    {
        $method = FulfillmentMethod::factory()->create(['base_cost' => 12.5]);
        $state = State::factory()->create();

        $this->assertSame('12.500000', $method->estimateCostFor($state, null));
    }

    public function test_a_state_wide_zone_rate_overrides_the_base_cost(): void
    {
        $method = FulfillmentMethod::factory()->create(['base_cost' => 12.5]);
        $state = State::factory()->create();
        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $method->id,
            'state_id' => $state->id,
            'municipality_id' => null,
            'cost' => 5,
        ]);

        $this->assertSame('5.000000', $method->estimateCostFor($state, null));
    }

    public function test_a_municipality_specific_rate_overrides_the_state_wide_one(): void
    {
        $method = FulfillmentMethod::factory()->create(['base_cost' => 12.5]);
        $state = State::factory()->create();
        $municipality = Municipality::factory()->create(['state_id' => $state->id]);
        $otherMunicipality = Municipality::factory()->create(['state_id' => $state->id]);

        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $method->id,
            'state_id' => $state->id,
            'municipality_id' => null,
            'cost' => 5,
        ]);
        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $method->id,
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'cost' => 8,
        ]);

        $this->assertSame('8.000000', $method->estimateCostFor($state, $municipality));
        // A different municipality in the same state still gets the state-wide rate.
        $this->assertSame('5.000000', $method->estimateCostFor($state, $otherMunicipality));
    }

    public function test_an_explicit_null_zone_rate_means_a_coordinar_even_with_a_base_cost(): void
    {
        $method = FulfillmentMethod::factory()->create(['base_cost' => 12.5]);
        $state = State::factory()->create();
        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $method->id,
            'state_id' => $state->id,
            'municipality_id' => null,
            'cost' => null,
        ]);

        $this->assertNull($method->estimateCostFor($state, null));
    }

    public function test_no_base_cost_and_no_zone_rate_means_a_coordinar(): void
    {
        $method = FulfillmentMethod::factory()->create(['base_cost' => null]);
        $state = State::factory()->create();

        $this->assertNull($method->estimateCostFor($state, null));
    }

    /**
     * base_cost is a flat rate by design — it does not depend on a zone, so it
     * applies even with no destination given. The storefront controller
     * layers its own "no state, no price" rule on top of this for the
     * pre-checkout listing; the provider itself has no such restriction.
     */
    public function test_a_flat_base_cost_applies_even_with_no_state_given(): void
    {
        $method = FulfillmentMethod::factory()->create(['base_cost' => 12.5]);

        $this->assertSame('12.500000', $method->estimateCostFor(null, null));
    }

    public function test_no_base_cost_and_no_state_given_means_a_coordinar(): void
    {
        $method = FulfillmentMethod::factory()->create(['base_cost' => null]);

        $this->assertNull($method->estimateCostFor(null, null));
    }

    public function test_requires_tracking_code_reads_from_the_stored_configuration(): void
    {
        $courier = FulfillmentMethod::factory()->courierManual()->create(['requires_tracking_code' => false]);
        $delivery = FulfillmentMethod::factory()->create(['requires_tracking_code' => true]);

        $this->assertFalse($courier->provider()->requiresTrackingCode());
        $this->assertTrue($delivery->provider()->requiresTrackingCode());
    }

    public function test_type_matches_the_method_type(): void
    {
        $this->assertSame(
            FulfillmentMethodType::DeliveryPropio,
            FulfillmentMethod::factory()->create()->provider()->type()
        );
        $this->assertSame(
            FulfillmentMethodType::RetiroEnTienda,
            FulfillmentMethod::factory()->retiroEnTienda()->create()->provider()->type()
        );
        $this->assertSame(
            FulfillmentMethodType::CourierManual,
            FulfillmentMethod::factory()->courierManual()->create()->provider()->type()
        );
    }
}
