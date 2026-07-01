<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_address_chain_resolves_consistently(): void
    {
        $state = State::create(['name' => 'Miranda', 'code' => 'MI']);
        $municipality = Municipality::create(['state_id' => $state->id, 'name' => 'Sucre']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'name' => 'Petare']);

        $customer = Customer::factory()->create([
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
        ]);

        $this->assertTrue($customer->state->is($state));
        $this->assertTrue($customer->municipality->is($municipality));
        $this->assertTrue($customer->parish->is($parish));

        $this->assertSame($customer->state_id, $customer->municipality->state->id);
        $this->assertSame($customer->municipality_id, $customer->parish->municipality->id);
    }
}
