<?php

namespace Database\Factories;

use App\Domain\Enums\DocumentType;
use App\Domain\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $baseAmount = fake()->randomFloat(6, 10, 500);

        return [
            'customer_id' => null,
            'status' => OrderStatus::PendingPayment,
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'customer_name' => fake()->name(),
            'customer_phone' => '+58'.fake()->numerify('##########'),
            'document_type' => DocumentType::Cedula,
            'document_number' => fake()->numerify('########'),
            'state_id' => null,
            'municipality_id' => null,
            'parish_id' => null,
            'address_reference' => fake()->address(),
            'base_currency_id' => Currency::factory(),
            'base_amount' => $baseAmount,
            'payment_currency_id' => Currency::factory(),
            'exchange_rate_applied' => 1,
            'payment_amount' => $baseAmount,
            'payment_method_id' => null,
            'fulfillment_method_id' => null,
        ];
    }
}
