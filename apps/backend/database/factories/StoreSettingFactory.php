<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreSetting>
 */
class StoreSettingFactory extends Factory
{
    protected $model = StoreSetting::class;

    public function definition(): array
    {
        return [
            'store_name' => 'Tienda Test',
            'logo_path' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'base_currency_id' => Currency::factory(),
            'whatsapp_number' => '+584121234567',
        ];
    }

    /**
     * A store that accepts exactly the currencies given, with the first one as
     * its base — the shape every settings test needs before it can start.
     *
     * @param  array<int, Currency>  $currencies
     */
    public function accepting(array $currencies): static
    {
        return $this
            ->state(['base_currency_id' => $currencies[0]->id])
            ->afterCreating(fn (StoreSetting $store) => $store->enabledCurrencies()->sync(
                collect($currencies)->pluck('id')->all(),
            ));
    }
}
