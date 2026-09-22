<?php

namespace Database\Factories;

use App\Models\FulfillmentMethod;
use App\Models\FulfillmentZoneRate;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentZoneRate>
 */
class FulfillmentZoneRateFactory extends Factory
{
    protected $model = FulfillmentZoneRate::class;

    public function definition(): array
    {
        return [
            'fulfillment_method_id' => FulfillmentMethod::factory(),
            'state_id' => State::factory(),
            'municipality_id' => null,
            'cost' => fake()->randomFloat(6, 1, 30),
        ];
    }
}
