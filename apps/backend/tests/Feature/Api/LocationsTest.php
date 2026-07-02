<?php

namespace Tests\Feature\Api;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_states_ordered_by_name(): void
    {
        State::create(['name' => 'Zulia', 'code' => 'ZU']);
        State::create(['name' => 'Miranda', 'code' => 'MI']);

        $response = $this->getJson('/api/locations/states');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertSame('Miranda', $response->json('data.0.name'));
        $this->assertSame('Zulia', $response->json('data.1.name'));
    }

    public function test_returns_municipalities_for_a_given_state(): void
    {
        $miranda = State::create(['name' => 'Miranda', 'code' => 'MI']);
        $zulia = State::create(['name' => 'Zulia', 'code' => 'ZU']);

        Municipality::create(['state_id' => $miranda->id, 'name' => 'Sucre']);
        Municipality::create(['state_id' => $zulia->id, 'name' => 'Maracaibo']);

        $response = $this->getJson("/api/locations/municipalities?state_id={$miranda->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('Sucre', $response->json('data.0.name'));
    }

    public function test_municipalities_requires_a_valid_state_id(): void
    {
        $response = $this->getJson('/api/locations/municipalities?state_id=999999');

        $response->assertStatus(422);
        $response->assertJson(['error' => ['code' => 'validation_error']]);
    }

    public function test_returns_parishes_for_a_given_municipality(): void
    {
        $state = State::create(['name' => 'Miranda', 'code' => 'MI']);
        $sucre = Municipality::create(['state_id' => $state->id, 'name' => 'Sucre']);
        $baruta = Municipality::create(['state_id' => $state->id, 'name' => 'Baruta']);

        Parish::create(['municipality_id' => $sucre->id, 'name' => 'Petare']);
        Parish::create(['municipality_id' => $baruta->id, 'name' => 'El Cafetal']);

        $response = $this->getJson("/api/locations/parishes?municipality_id={$sucre->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('Petare', $response->json('data.0.name'));
    }

    public function test_parishes_requires_a_valid_municipality_id(): void
    {
        $response = $this->getJson('/api/locations/parishes?municipality_id=999999');

        $response->assertStatus(422);
        $response->assertJson(['error' => ['code' => 'validation_error']]);
    }
}
