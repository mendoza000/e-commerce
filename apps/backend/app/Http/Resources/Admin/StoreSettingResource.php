<?php

namespace App\Http\Resources\Admin;

use App\Models\Currency;
use App\Models\StoreSetting;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The store's configuration as the settings screen needs it.
 *
 * The one thing it adds beyond the stored columns is `has_rate` per enabled
 * currency. Changing the base currency is allowed even when no rate exists yet
 * for a pair, because forbidding it would make the change impossible to ever
 * carry out — but the panel has to be able to say "you now sell in USD and
 * there is no USD/VES rate", which is otherwise invisible until a customer
 * hits checkout.
 *
 * @mixin StoreSetting
 */
class StoreSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrl(),
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'whatsapp_number' => $this->whatsapp_number,
            'base_currency' => $this->whenLoaded('baseCurrency', fn () => [
                'id' => $this->baseCurrency->id,
                'code' => $this->baseCurrency->code,
                'symbol' => $this->baseCurrency->symbol,
                'decimal_places' => $this->baseCurrency->decimal_places,
            ]),
            'enabled_currencies' => $this->whenLoaded(
                'enabledCurrencies',
                fn () => $this->enabledCurrencies->map(fn (Currency $currency) => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_places' => $currency->decimal_places,
                    'is_base' => $currency->is($this->baseCurrency),
                    'has_rate' => $this->hasRateFor($currency),
                ])->values(),
            ),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * The base currency always "has" a rate against itself; every other
     * enabled currency needs a row in `exchange_rates` before checkout can
     * quote in it.
     */
    private function hasRateFor(Currency $currency): bool
    {
        if ($currency->is($this->baseCurrency)) {
            return true;
        }

        return app(ExchangeRateService::class)->latestRate($this->baseCurrency, $currency) !== null;
    }
}
