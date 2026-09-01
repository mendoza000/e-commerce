<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            // Two letters + two digits, never a real ISO code. `currencies.code`
            // is unique, and faker's unique() only tracks what it generated
            // itself — so a random currencyCode() could collide with a USD/VES
            // row a test created explicitly.
            'code' => fake()->unique()->regexify('[A-Z]{2}[0-9]{2}'),
            'name' => fake()->currencyCode(),
            'symbol' => fake()->randomElement(['$', '€', '£', '¥', 'Bs.']),
            'decimal_places' => 2,
            'is_active' => true,
        ];
    }
}
