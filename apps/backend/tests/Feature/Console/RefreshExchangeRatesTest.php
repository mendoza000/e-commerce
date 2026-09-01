<?php

namespace Tests\Feature\Console;

use App\Domain\Enums\ExchangeRateMode;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usdt;

    private Currency $ves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usdt = Currency::factory()->create(['code' => 'USDT']);
        $this->ves = Currency::factory()->create(['code' => 'VES']);
    }

    private function automaticSetting(array $overrides = []): ExchangeRateSetting
    {
        return ExchangeRateSetting::factory()->criptoya()->create(array_merge([
            'from_currency_id' => $this->usdt->id,
            'to_currency_id' => $this->ves->id,
        ], $overrides));
    }

    public function test_it_stores_a_rate_for_every_due_automatic_pair(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['totalAsk' => 737.25])]);

        $this->automaticSetting();

        $this->artisan('exchange-rates:refresh')
            ->expectsOutputToContain('USDT/VES')
            ->assertSuccessful();

        $this->assertSame(1, ExchangeRate::query()->count());
    }

    public function test_manual_pairs_are_left_alone(): void
    {
        Http::fake();

        $this->automaticSetting(['mode' => ExchangeRateMode::Manual, 'provider' => 'manual']);

        $this->artisan('exchange-rates:refresh')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, ExchangeRate::query()->count());
    }

    public function test_inactive_pairs_are_skipped(): void
    {
        Http::fake();

        $this->automaticSetting(['is_active' => false]);

        $this->artisan('exchange-rates:refresh')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_pair_that_ran_recently_is_not_fetched_again(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['totalAsk' => 737])]);

        Carbon::setTestNow('2026-08-31 10:00:00');
        $this->automaticSetting(['frequency_minutes' => 60]);

        $this->artisan('exchange-rates:refresh')->assertSuccessful();

        Carbon::setTestNow('2026-08-31 10:30:00');
        $this->artisan('exchange-rates:refresh')->assertSuccessful();

        // Second run was inside the pair's window: still a single stored rate.
        $this->assertSame(1, ExchangeRate::query()->count());

        Carbon::setTestNow();
    }

    public function test_a_failing_source_does_not_fail_the_command(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(null, 500)]);

        $setting = $this->automaticSetting();

        $this->artisan('exchange-rates:refresh')
            ->expectsOutputToContain('0 rate(s), 1 failed')
            ->assertSuccessful();

        $this->assertSame(0, ExchangeRate::query()->count());
        $this->assertNotNull($setting->fresh()->last_error);
    }

    public function test_one_broken_pair_does_not_stop_the_others(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);

        Http::fake([
            'criptoya.com/*/usdt/*' => Http::response(null, 500),
            'criptoya.com/*/usd/*' => Http::response(['totalAsk' => 740]),
        ]);

        $this->automaticSetting();
        $this->automaticSetting(['from_currency_id' => $usd->id]);

        $this->artisan('exchange-rates:refresh')
            ->expectsOutputToContain('1 rate(s), 1 failed')
            ->assertSuccessful();

        $this->assertSame(1, ExchangeRate::query()->count());
    }
}
