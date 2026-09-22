<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\ExchangeRateProviderType;
use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Domain\ExchangeRates\ExchangeRateProviderRegistry;
use App\Domain\ExchangeRates\Providers\CriptoYaRateProvider;
use App\Domain\ExchangeRates\Providers\ManualRateProvider;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExchangeRateProviderRegistryTest extends TestCase
{
    private ExchangeRateProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ExchangeRateProviderRegistry;
    }

    public function test_every_declared_type_resolves_to_its_own_provider(): void
    {
        foreach (ExchangeRateProviderType::cases() as $type) {
            $this->assertInstanceOf($type->providerClass(), $this->registry->for($type->value));
        }
    }

    public function test_criptoya_resolves_to_the_http_provider(): void
    {
        $this->assertInstanceOf(CriptoYaRateProvider::class, $this->registry->for('criptoya'));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function unknownProviderProvider(): array
    {
        return [
            'no provider configured' => [null],
            'empty string' => [''],
            'a provider that was removed from the code' => ['bcv_scraper'],
            'a typo' => ['criptoyaa'],
        ];
    }

    /**
     * Falling back to manual is the safe default: the worst case is that the
     * admin keeps typing the rate in, never that a pair silently starts taking
     * rates from an unexpected source.
     */
    #[DataProvider('unknownProviderProvider')]
    public function test_an_unknown_provider_falls_back_to_manual(?string $provider): void
    {
        $this->assertInstanceOf(ManualRateProvider::class, $this->registry->for($provider));
    }

    public function test_the_manual_fallback_never_calls_anything_external(): void
    {
        Http::fake();

        $provider = $this->registry->for('esto_no_existe');

        $this->assertFalse($provider->isAutomatic());

        try {
            $provider->getRate(new Currency(['code' => 'USD']), new Currency(['code' => 'VES']));
        } catch (ExchangeRateUnavailable) {
            // Expected: there is nothing to fetch for a manual pair.
        }

        Http::assertNothingSent();
    }
}
