<?php

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\StoreSetting;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExchangeRateService;
    }

    public function test_latest_rate_returns_the_row_with_the_most_recent_effective_at(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $ves = Currency::factory()->create(['code' => 'VES']);

        ExchangeRate::create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $ves->id,
            'rate' => 36.5,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now()->subDay(),
            'created_by' => null,
        ]);

        $newest = ExchangeRate::create([
            'from_currency_id' => $usd->id,
            'to_currency_id' => $ves->id,
            'rate' => 40.0,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now(),
            'created_by' => null,
        ]);

        $latest = $this->service->latestRate($usd, $ves);

        $this->assertNotNull($latest);
        $this->assertTrue($latest->is($newest));
    }

    public function test_latest_rate_returns_null_when_no_rate_exists_for_the_pair(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $cop = Currency::factory()->create(['code' => 'COP']);

        $this->assertNull($this->service->latestRate($usd, $cop));
    }

    public function test_latest_rate_returns_null_for_a_currency_against_itself(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);

        $this->assertNull($this->service->latestRate($usd, $usd));
    }

    public function test_enabled_currencies_with_rates_marks_base_currency_and_resolves_others(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $ves = Currency::factory()->create(['code' => 'VES']);

        $rate = ExchangeRate::create([
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
        $store->load('baseCurrency', 'enabledCurrencies');

        $result = $this->service->enabledCurrenciesWithRates($store);

        $this->assertCount(2, $result);

        $byCode = collect($result)->keyBy(fn ($entry) => $entry['currency']->code);

        $this->assertTrue($byCode['USD']['is_base']);
        $this->assertNull($byCode['USD']['rate']);

        $this->assertFalse($byCode['VES']['is_base']);
        $this->assertTrue($byCode['VES']['rate']->is($rate));
    }

    public function test_enabled_currencies_with_rates_leaves_rate_null_when_none_exists(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $cop = Currency::factory()->create(['code' => 'COP']);

        $store = StoreSetting::create([
            'store_name' => 'Tienda Test',
            'base_currency_id' => $usd->id,
        ]);
        $store->enabledCurrencies()->sync([$usd->id, $cop->id]);
        $store->load('baseCurrency', 'enabledCurrencies');

        $result = $this->service->enabledCurrenciesWithRates($store);
        $byCode = collect($result)->keyBy(fn ($entry) => $entry['currency']->code);

        $this->assertNull($byCode['COP']['rate']);
    }
}
