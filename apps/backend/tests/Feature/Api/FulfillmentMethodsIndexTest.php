<?php

namespace Tests\Feature\Api;

use App\Models\FulfillmentMethod;
use App\Models\FulfillmentZoneRate;
use App\Models\Municipality;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentMethodsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_active_methods(): void
    {
        FulfillmentMethod::factory()->create();
        FulfillmentMethod::factory()->inactive()->create();

        $response = $this->getJson('/api/fulfillment-methods');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_no_state_given_means_no_priced_estimate(): void
    {
        FulfillmentMethod::factory()->create(['base_cost' => 5]);

        $response = $this->getJson('/api/fulfillment-methods');

        $response->assertOk()->assertJsonPath('data.0.estimated_cost', null);
    }

    public function test_a_state_prices_the_flat_rate_methods(): void
    {
        $state = State::factory()->create();
        FulfillmentMethod::factory()->create(['base_cost' => 5]);

        $response = $this->getJson("/api/fulfillment-methods?state_id={$state->id}");

        $response->assertOk()->assertJsonPath('data.0.estimated_cost', '5.000000');
    }

    public function test_a_zone_rate_overrides_the_base_cost_for_its_state(): void
    {
        $state = State::factory()->create();
        $method = FulfillmentMethod::factory()->create(['base_cost' => 5]);
        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $method->id,
            'state_id' => $state->id,
            'cost' => 12,
        ]);

        $response = $this->getJson("/api/fulfillment-methods?state_id={$state->id}");

        $response->assertOk()->assertJsonPath('data.0.estimated_cost', '12.000000');
    }

    public function test_pickup_in_store_is_always_free_regardless_of_the_state(): void
    {
        $state = State::factory()->create();
        FulfillmentMethod::factory()->retiroEnTienda()->create();

        $response = $this->getJson("/api/fulfillment-methods?state_id={$state->id}");

        $response->assertOk()->assertJsonPath('data.0.estimated_cost', '0.000000');
    }

    public function test_a_municipality_from_another_state_is_rejected(): void
    {
        $state = State::factory()->create();
        $otherState = State::factory()->create();
        $municipality = Municipality::factory()->create(['state_id' => $otherState->id]);

        $response = $this->getJson("/api/fulfillment-methods?state_id={$state->id}&municipality_id={$municipality->id}");

        $response->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['municipality_id']]]);
    }
}
