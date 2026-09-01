<?php

namespace Database\Factories;

use App\Domain\Enums\ExchangeRateMode;
use App\Domain\Enums\ExchangeRateProviderType;
use App\Models\Currency;
use App\Models\ExchangeRateSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRateSetting>
 */
class ExchangeRateSettingFactory extends Factory
{
    protected $model = ExchangeRateSetting::class;

    public function definition(): array
    {
        return [
            'from_currency_id' => Currency::factory(),
            'to_currency_id' => Currency::factory(),
            'mode' => ExchangeRateMode::Manual,
            'provider' => ExchangeRateProviderType::Manual->value,
            'frequency_minutes' => null,
            'reference_amount' => null,
            'is_active' => true,
        ];
    }

    public function criptoya(): static
    {
        return $this->state([
            'mode' => ExchangeRateMode::Automatic,
            'provider' => ExchangeRateProviderType::CriptoYa->value,
            'frequency_minutes' => 60,
            'reference_amount' => 100,
        ]);
    }
}
