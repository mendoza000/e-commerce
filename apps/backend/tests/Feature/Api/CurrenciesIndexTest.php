<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrenciesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enabled_currencies_with_base_marked_and_rate_resolved(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD', 'decimal_places' => 2]);
        $ves = Currency::factory()->create(['code' => 'VES', 'decimal_places' => 0]);

        ExchangeRate::create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $ves->id,
            'rate' => 40.0,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now(),
            'created_by' => null,
        ]);

        $store = StoreSetting::create([
            'store_name' => 'Tienda Test',
            'base_currency_id' => $usd->id,
        ]);
        $store->enabledCurrencies()->sync([$usd->id, $ves->id]);

        $response = $this->getJson('/api/currencies');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $data = collect($response->json('data'))->keyBy('code');

        $this->assertTrue($data['USD']['is_base']);
        $this->assertSame('1.000000', $data['USD']['rate']);

        $this->assertFalse($data['VES']['is_base']);
        $this->assertEquals(40.0, (float) $data['VES']['rate']);
        $this->assertSame(0, $data['VES']['decimal_places']);
    }

    public function test_non_base_currency_with_no_rate_returns_null_rate(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $cop = Currency::factory()->create(['code' => 'COP']);

        $store = StoreSetting::create([
            'store_name' => 'Tienda Test',
            'base_currency_id' => $usd->id,
        ]);
        $store->enabledCurrencies()->sync([$usd->id, $cop->id]);

        $response = $this->getJson('/api/currencies');

        $response->assertOk();
        $data = collect($response->json('data'))->keyBy('code');

        $this->assertNull($data['COP']['rate']);
    }
}
