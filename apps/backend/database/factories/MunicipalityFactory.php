<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Municipality>
 */
class MunicipalityFactory extends Factory
{
    protected $model = Municipality::class;

    public function definition(): array
    {
        return [
            'state_id' => State::factory(),
            'name' => fake()->unique()->city(),
        ];
    }
}
