<?php

namespace Database\Factories;

use App\Domain\Enums\DocumentType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => null,
            'phone' => '+58'.fake()->numerify('##########'),
            'document_type' => DocumentType::Cedula,
            'document_number' => fake()->unique()->numerify('########'),
            'state_id' => null,
            'municipality_id' => null,
            'parish_id' => null,
            'address_reference' => fake()->address(),
        ];
    }
}
