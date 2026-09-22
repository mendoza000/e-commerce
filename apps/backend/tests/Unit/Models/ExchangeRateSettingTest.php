<?php

namespace Tests\Unit\Models;

use App\Domain\Enums\ExchangeRateMode;
use App\Domain\ExchangeRates\Providers\CriptoYaRateProvider;
use App\Domain\ExchangeRates\Providers\ManualRateProvider;
use App\Models\Currency;
use App\Models\ExchangeRateSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExchangeRateSettingTest extends TestCase
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

    private function setting(array $overrides = [], bool $automatic = true): ExchangeRateSetting
    {
        $factory = ExchangeRateSetting::factory();

        return ($automatic ? $factory->criptoya() : $factory)->create(array_merge([
            'from_currency_id' => $this->usdt->id,
            'to_currency_id' => $this->ves->id,
        ], $overrides));
    }

    public function test_the_pair_label_reads_as_the_admin_would_write_it(): void
    {
        $this->assertSame('USDT/VES', $this->setting()->pairLabel());
    }

    public function test_the_rate_provider_is_resolved_from_the_provider_column(): void
    {
        // One pair per currency combination: the table enforces it.
        $automatic = $this->setting();
        $manual = $this->setting(['from_currency_id' => Currency::factory()->create()->id], automatic: false);

        $this->assertInstanceOf(CriptoYaRateProvider::class, $automatic->rateProvider());
        $this->assertInstanceOf(ManualRateProvider::class, $manual->rateProvider());
    }

    public function test_a_pair_that_never_ran_is_due_immediately(): void
    {
        $this->assertTrue($this->setting(['last_run_at' => null])->isDueForRefresh());
    }

    public function test_a_pair_without_a_frequency_is_due_on_every_run(): void
    {
        // The scheduler's own cadence becomes the frequency.
        $setting = $this->setting(['frequency_minutes' => null]);
        $setting->markRefreshed();

        $this->assertTrue($setting->fresh()->isDueForRefresh());
    }

    public function test_a_manual_pair_is_never_due(): void
    {
        $setting = $this->setting(['mode' => ExchangeRateMode::Manual]);

        $this->assertFalse($setting->isDueForRefresh());
    }

    public function test_an_inactive_pair_is_never_due(): void
    {
        $this->assertFalse($this->setting(['is_active' => false])->isDueForRefresh());
    }

    public function test_a_pair_becomes_due_again_exactly_after_its_frequency(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');

        $setting = $this->setting(['frequency_minutes' => 30]);
        $setting->markRefreshed();

        Carbon::setTestNow('2026-08-31 10:29:00');
        $this->assertFalse($setting->fresh()->isDueForRefresh());

        Carbon::setTestNow('2026-08-31 10:31:00');
        $this->assertTrue($setting->fresh()->isDueForRefresh());

        Carbon::setTestNow();
    }

    public function test_mark_refreshed_clears_a_previous_failure(): void
    {
        $setting = $this->setting();
        $setting->markFailed('CriptoYa respondió 503.');

        $setting->markRefreshed();
        $setting->refresh();

        $this->assertNull($setting->last_error);
        $this->assertNull($setting->last_error_at);
        $this->assertNotNull($setting->last_run_at);
    }

    public function test_mark_failed_keeps_the_message_for_the_admin_to_read(): void
    {
        $setting = $this->setting();

        $setting->markFailed('CriptoYa respondió 503.');
        $setting->refresh();

        $this->assertSame('CriptoYa respondió 503.', $setting->last_error);
        $this->assertNotNull($setting->last_error_at);
        // A failure counts as a run, so a dead source is not retried every tick.
        $this->assertNotNull($setting->last_run_at);
    }

    public function test_the_automatic_scope_only_returns_active_automatic_pairs(): void
    {
        $automatic = $this->setting();
        $this->setting(['mode' => ExchangeRateMode::Manual, 'from_currency_id' => Currency::factory()->create()->id]);
        $this->setting(['is_active' => false, 'from_currency_id' => Currency::factory()->create()->id]);

        $this->assertSame([$automatic->id], ExchangeRateSetting::query()->automatic()->pluck('id')->all());
    }
}
