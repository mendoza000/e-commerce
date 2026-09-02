<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
{
    protected $model = PaymentProof::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'disk' => 'local',
            'path' => 'payment-proofs/'.fake()->uuid().'.jpg',
            'original_name' => 'comprobante.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(20_000, 400_000),
            'reference' => fake()->numerify('##########'),
            'submitted_at' => now(),
        ];
    }
}
