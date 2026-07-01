<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    private array $optionsMap = [];

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'base_price' => fake()->randomFloat(6, 5, 200),
            'is_active' => true,
        ];
    }

    public function withVariants(array $optionsMap): static
    {
        $factory = clone $this;
        $factory->optionsMap = $optionsMap;

        return $factory->afterCreating(function (Product $product) use ($optionsMap) {
            if ($optionsMap === []) {
                ProductVariant::factory()->create([
                    'product_id' => $product->id,
                ]);

                return;
            }

            $valueIdsPerOption = [];

            foreach (array_values($optionsMap) as $optionPosition => $values) {
                $optionName = array_keys($optionsMap)[$optionPosition];

                $option = ProductOption::factory()->create([
                    'product_id' => $product->id,
                    'name' => $optionName,
                    'position' => $optionPosition,
                ]);

                $valueIds = [];

                foreach (array_values($values) as $valuePosition => $value) {
                    $optionValue = ProductOptionValue::factory()->create([
                        'product_option_id' => $option->id,
                        'value' => $value,
                        'position' => $valuePosition,
                    ]);

                    $valueIds[] = $optionValue->id;
                }

                $valueIdsPerOption[] = $valueIds;
            }

            $combinations = array_reduce(
                $valueIdsPerOption,
                function (array $carry, array $valueIds) {
                    $result = [];

                    foreach ($carry as $combination) {
                        foreach ($valueIds as $valueId) {
                            $result[] = [...$combination, $valueId];
                        }
                    }

                    return $result;
                },
                [[]]
            );

            foreach ($combinations as $combination) {
                $variant = ProductVariant::factory()->create([
                    'product_id' => $product->id,
                ]);

                $variant->optionValues()->attach($combination);
            }
        });
    }
}
