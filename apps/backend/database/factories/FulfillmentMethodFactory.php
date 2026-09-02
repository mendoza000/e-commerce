<?php

namespace Database\Factories;

use App\Domain\Enums\FulfillmentMethodType;
use App\Models\Currency;
use App\Models\FulfillmentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentMethod>
 */
class FulfillmentMethodFactory extends Factory
{
    protected $model = FulfillmentMethod::class;

    public function definition(): array
    {
        return [
            'type' => FulfillmentMethodType::DeliveryPropio,
            'label' => 'Delivery propio',
            'requires_tracking_code' => false,
            'base_cost' => null,
            'currency_id' => Currency::factory(),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function retiroEnTienda(): static
    {
        return $this->state([
            'type' => FulfillmentMethodType::RetiroEnTienda,
            'label' => 'Retiro en tienda',
            'requires_tracking_code' => false,
            'base_cost' => null,
        ]);
    }

    public function courierManual(): static
    {
        return $this->state([
            'type' => FulfillmentMethodType::CourierManual,
            'label' => 'Courier nacional',
            'requires_tracking_code' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
