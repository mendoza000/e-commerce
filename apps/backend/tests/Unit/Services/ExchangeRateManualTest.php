<?php

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ExchangeRateService::storeManual() — the admin typing a rate in.
 *
 * The property worth protecting is that it only ever appends. `exchange_rates`
 * is what an order's frozen rate is justified against, so a row that changes
 * after the fact makes a correct order look wrong.
 */
class ExchangeRateManualTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExchangeRateService;
    }

    public function test_it_stores_the_rate_with_its_source_and_its_author(): void
    {
        $admin = User::factory()->owner()->create();
        $usd = Currency::factory()->create(['code' => 'USD']);
        $ves = Currency::factory()->create(['code' => 'VES']);

        $rate = $this->service->storeManual($usd, $ves, '737.500000', '100.000000', $admin);

        $this->assertSame('737.500000', $rate->rate);
        $this->assertSame('manual', $rate->source);
        $this->assertSame('100.000000', $rate->reference_amount);
        $this->assertSame($admin->id, $rate->created_by);
        $this->assertNotNull($rate->effective_at);
    }

    /**
     * The mirror image of refresh(), which leaves the author null on purpose:
     * there, nobody decided the number, a provider reported it.
     */
    public function test_a_rate_with_no_admin_behind_it_has_no_author(): void
    {
        $usd = Currency::factory()->create();
        $ves = Currency::factory()->create();

        $this->assertNull($this->service->storeManual($usd, $ves, '40')->created_by);
    }

    public function test_registering_a_second_rate_appends_instead_of_overwriting(): void
    {
        $usd = Currency::factory()->create();
        $ves = Currency::factory()->create();

        $first = $this->service->storeManual($usd, $ves, '700');
        $second = $this->service->storeManual($usd, $ves, '740');

        $this->assertSame(2, ExchangeRate::query()->count());
        $this->assertSame('700.000000', $first->fresh()->rate);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_the_newest_rate_is_the_one_that_prices_the_store(): void
    {
        $usd = Currency::factory()->create();
        $ves = Currency::factory()->create();

        $this->service->storeManual($usd, $ves, '700');
        $this->travel(1)->minutes();
        $this->service->storeManual($usd, $ves, '740');

        $this->assertSame('740.000000', $this->service->latestRate($usd, $ves)->rate);
    }

    public function test_a_currency_cannot_be_exchanged_for_itself(): void
    {
        $usd = Currency::factory()->create();

        try {
            $this->service->storeManual($usd, $usd, '1');
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_currency_id', $e->errors());
        }

        $this->assertSame(0, ExchangeRate::query()->count());
    }
}
