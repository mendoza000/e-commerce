<?php

namespace Database\Factories;

use App\Domain\Enums\ExchangeRateProviderType;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'from_currency_id' => Currency::factory(),
            'to_currency_id' => Currency::factory(),
            'rate' => fake()->randomFloat(6, 1, 300),
            'source' => ExchangeRateProviderType::Manual->value,
            'reference_amount' => null,
            'effective_at' => now(),
            'created_by' => null,
        ];
    }

    /** A rate a provider produced: no author, and its own source name. */
    public function automatic(): static
    {
        return $this->state([
            'source' => ExchangeRateProviderType::CriptoYa->value,
            'created_by' => null,
        ]);
    }
}
