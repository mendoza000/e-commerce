<?php

namespace Database\Factories;

use App\Domain\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'sku' => strtoupper(fake()->bothify('########')),
            'type' => InventoryMovementType::Sale,
            'quantity_change' => -fake()->numberBetween(1, 5),
            'reason' => null,
            'order_id' => null,
            'created_by' => null,
        ];
    }

    public function release(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InventoryMovementType::Release,
            'quantity_change' => fake()->numberBetween(1, 5),
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InventoryMovementType::Adjustment,
            'quantity_change' => fake()->numberBetween(1, 5),
        ]);
    }
}
