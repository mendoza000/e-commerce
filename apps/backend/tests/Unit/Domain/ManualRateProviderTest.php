<?php

namespace Tests\Unit\Domain;

use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Domain\ExchangeRates\Providers\ManualRateProvider;
use App\Models\Currency;
use PHPUnit\Framework\TestCase;

class ManualRateProviderTest extends TestCase
{
    private ManualRateProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new ManualRateProvider;
    }

    /**
     * The scheduled refresh checks this before doing anything, which is what
     * keeps a job from ever overwriting a rate the admin typed in.
     */
    public function test_it_is_not_an_automatic_source(): void
    {
        $this->assertFalse($this->provider->isAutomatic());
    }

    public function test_rates_it_produces_are_traceable_as_manual(): void
    {
        $this->assertSame('manual', $this->provider->getSourceName());
    }

    public function test_asking_it_to_fetch_a_rate_is_an_error_not_a_silent_zero(): void
    {
        $this->expectException(ExchangeRateUnavailable::class);
        $this->expectExceptionMessageMatches('/administrador/');

        $this->provider->getRate(new Currency(['code' => 'USD']), new Currency(['code' => 'VES']));
    }
}
