<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\ExchangeRateMode;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateSetting;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateRefreshTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateService $service;

    private Currency $usdt;

    private Currency $ves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ExchangeRateService;
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

    public function test_a_successful_fetch_is_stored_with_its_source(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['ask' => 730.5, 'totalAsk' => 737.25])]);

        $setting = $this->automaticSetting();

        $rate = $this->service->refresh($setting);

        $this->assertNotNull($rate);
        // totalAsk wins over ask: it is what the buyer actually pays.
        $this->assertSame('737.250000', $rate->rate);
        $this->assertSame('criptoya:binancep2p', $rate->source);
        $this->assertNull($setting->fresh()->last_error);
        $this->assertNotNull($setting->fresh()->last_run_at);
    }

    public function test_the_configured_reference_amount_is_used_as_the_quote_volume(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['totalAsk' => 737])]);

        $this->service->refresh($this->automaticSetting(['reference_amount' => 100]));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/binancep2p/usdt/ves/100'));
    }

    public function test_a_failing_source_stores_nothing_and_records_the_incident(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(null, 503)]);

        $setting = $this->automaticSetting();

        $this->assertNull($this->service->refresh($setting));

        // Nothing written: checkout keeps using whatever rate it had before.
        $this->assertSame(0, ExchangeRate::query()->count());
        $this->assertNotNull($setting->fresh()->last_error_at);
        $this->assertStringContainsString('503', $setting->fresh()->last_error);
    }

    public function test_an_unusable_payload_is_treated_as_a_failure(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['ask' => 0])]);

        $setting = $this->automaticSetting();

        $this->assertNull($this->service->refresh($setting));
        $this->assertSame(0, ExchangeRate::query()->count());
    }

    public function test_a_manual_pair_is_never_fetched(): void
    {
        Http::fake();

        $setting = $this->automaticSetting([
            'mode' => ExchangeRateMode::Manual,
            'provider' => 'manual',
        ]);

        $this->assertNull($this->service->refresh($setting));

        Http::assertNothingSent();
    }

    public function test_a_pair_is_not_due_again_until_its_frequency_has_elapsed(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');

        $setting = $this->automaticSetting(['frequency_minutes' => 60]);
        $setting->markRefreshed();

        Carbon::setTestNow('2026-08-31 10:30:00');
        $this->assertFalse($setting->fresh()->isDueForRefresh());

        Carbon::setTestNow('2026-08-31 11:01:00');
        $this->assertTrue($setting->fresh()->isDueForRefresh());

        Carbon::setTestNow();
    }

    public function test_a_failed_attempt_also_counts_against_the_frequency(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');

        $setting = $this->automaticSetting(['frequency_minutes' => 60]);
        $setting->markFailed('CriptoYa respondió 503.');

        // A source that is down must not be hammered on every scheduler tick.
        Carbon::setTestNow('2026-08-31 10:05:00');
        $this->assertFalse($setting->fresh()->isDueForRefresh());

        Carbon::setTestNow();
    }
}
