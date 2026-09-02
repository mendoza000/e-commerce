<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Currency;
use App\Models\FulfillmentMethod;
use App\Models\FulfillmentZoneRate;
use App\Models\Municipality;
use App\Models\State;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentZoneRateManagementTest extends TestCase
{
    use RefreshDatabase;

    private FulfillmentMethod $method;

    private State $state;

    private Municipality $municipality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();

        $usd = Currency::factory()->create(['code' => 'USD']);
        StoreSetting::factory()->accepting([$usd])->create();

        $this->method = FulfillmentMethod::factory()->create(['currency_id' => $usd->id, 'base_cost' => 10]);
        $this->state = State::factory()->create(['name' => 'Miranda']);
        $this->municipality = Municipality::factory()->create(['state_id' => $this->state->id, 'name' => 'Sucre']);
    }

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    public function test_it_creates_a_state_wide_rate(): void
    {
        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'cost' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.state.id', $this->state->id)
            ->assertJsonPath('data.municipality', null)
            ->assertJsonPath('data.cost', '5.000000');
    }

    public function test_it_creates_a_municipality_specific_rate(): void
    {
        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'municipality_id' => $this->municipality->id,
                'cost' => 8,
            ])
            ->assertCreated()
            ->assertJsonPath('data.municipality.id', $this->municipality->id)
            ->assertJsonPath('data.cost', '8.000000');
    }

    public function test_a_null_cost_means_a_coordinar(): void
    {
        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'cost' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.cost', null);
    }

    public function test_a_municipality_from_another_state_is_rejected(): void
    {
        $otherState = State::factory()->create();
        $otherMunicipality = Municipality::factory()->create(['state_id' => $otherState->id]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'municipality_id' => $otherMunicipality->id,
                'cost' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['municipality_id']]]);
    }

    public function test_a_duplicate_state_wide_zone_is_rejected(): void
    {
        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $this->method->id,
            'state_id' => $this->state->id,
            'municipality_id' => null,
        ]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'cost' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['state_id']]]);
    }

    public function test_a_duplicate_municipality_zone_is_rejected(): void
    {
        FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $this->method->id,
            'state_id' => $this->state->id,
            'municipality_id' => $this->municipality->id,
        ]);

        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'municipality_id' => $this->municipality->id,
                'cost' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['state_id']]]);
    }

    public function test_the_same_state_can_have_both_a_state_wide_and_a_municipality_specific_rate(): void
    {
        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'cost' => 5,
            ])
            ->assertCreated();

        $this->actingAs($this->owner())
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'municipality_id' => $this->municipality->id,
                'cost' => 8,
            ])
            ->assertCreated();

        $this->assertSame(2, $this->method->zoneRates()->count());
    }

    public function test_it_updates_the_cost(): void
    {
        $rate = FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $this->method->id,
            'state_id' => $this->state->id,
            'cost' => 5,
        ]);

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/zone-rates/{$rate->id}", ['cost' => 9])
            ->assertOk()
            ->assertJsonPath('data.cost', '9.000000');
    }

    public function test_the_zone_of_a_rate_cannot_be_changed(): void
    {
        $rate = FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $this->method->id,
            'state_id' => $this->state->id,
        ]);
        $otherState = State::factory()->create();

        $this->actingAs($this->owner())
            ->patchJson("/api/admin/zone-rates/{$rate->id}", ['state_id' => $otherState->id])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['state_id']]]);

        $this->assertSame($this->state->id, $rate->fresh()->state_id);
    }

    public function test_it_deletes_a_rate_falling_back_to_the_base_cost(): void
    {
        $rate = FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $this->method->id,
            'state_id' => $this->state->id,
            'cost' => 5,
        ]);

        $this->actingAs($this->owner())
            ->deleteJson("/api/admin/zone-rates/{$rate->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('fulfillment_zone_rates', ['id' => $rate->id]);
        $this->assertSame('10.000000', $this->method->fresh()->estimateCostFor($this->state, null));
    }

    public function test_staff_cannot_manage_zone_rates(): void
    {
        $staff = User::factory()->staff()->create();
        $rate = FulfillmentZoneRate::factory()->create([
            'fulfillment_method_id' => $this->method->id,
            'state_id' => $this->state->id,
        ]);

        $this->actingAs($staff)
            ->getJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates")
            ->assertStatus(403);

        $this->actingAs($staff)
            ->postJson("/api/admin/fulfillment-methods/{$this->method->id}/zone-rates", [
                'state_id' => $this->state->id,
                'cost' => 5,
            ])
            ->assertStatus(403);

        $this->actingAs($staff)->patchJson("/api/admin/zone-rates/{$rate->id}", ['cost' => 1])->assertStatus(403);
        $this->actingAs($staff)->deleteJson("/api/admin/zone-rates/{$rate->id}")->assertStatus(403);
    }
}
