<?php

namespace Tests\Unit\Domain;

use App\Domain\ExchangeRates\Exceptions\ExchangeRateUnavailable;
use App\Domain\ExchangeRates\Providers\CriptoYaRateProvider;
use App\Models\Currency;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The provider is tested in isolation: it only turns an HTTP response into a
 * decimal string. Persisting the result and recording failures belongs to
 * ExchangeRateService (see ExchangeRateRefreshTest).
 */
class CriptoYaRateProviderTest extends TestCase
{
    private CriptoYaRateProvider $provider;

    private Currency $usdt;

    private Currency $ves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new CriptoYaRateProvider;

        // No database needed: the provider only reads the currency codes.
        $this->usdt = new Currency(['code' => 'USDT']);
        $this->ves = new Currency(['code' => 'VES']);
    }

    public function test_it_is_an_automatic_source(): void
    {
        $this->assertTrue($this->provider->isAutomatic());
    }

    public function test_the_source_name_records_which_exchange_was_used(): void
    {
        config(['services.criptoya.exchange' => 'binancep2p']);

        $this->assertSame('criptoya:binancep2p', $this->provider->getSourceName());
    }

    public function test_it_builds_the_documented_url_from_the_currency_codes(): void
    {
        config([
            'services.criptoya.base_url' => 'https://criptoya.com/api',
            'services.criptoya.exchange' => 'binancep2p',
        ]);
        Http::fake(['criptoya.com/*' => Http::response(['totalAsk' => 737])]);

        $this->provider->getRate($this->usdt, $this->ves, '100.000000');

        Http::assertSent(
            fn ($request) => $request->url() === 'https://criptoya.com/api/binancep2p/usdt/ves/100'
        );
    }

    public function test_a_trailing_slash_in_the_base_url_does_not_produce_a_double_slash(): void
    {
        config(['services.criptoya.base_url' => 'https://criptoya.com/api/']);
        Http::fake(['criptoya.com/*' => Http::response(['totalAsk' => 737])]);

        $this->provider->getRate($this->usdt, $this->ves, '1');

        Http::assertSent(fn ($request) => ! str_contains(
            str_replace('https://', '', $request->url()),
            '//'
        ));
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function volumeProvider(): array
    {
        return [
            'no reference amount falls back to 1' => [null, '1'],
            'decimal padding is trimmed' => ['100.000000', '100'],
            'a real decimal keeps its fraction' => ['1.500000', '1.5'],
            'zero is not a usable volume' => ['0.000000', '1'],
            'a negative amount is not a usable volume' => ['-50.000000', '1'],
            'garbage is not a usable volume' => ['cien', '1'],
        ];
    }

    #[DataProvider('volumeProvider')]
    public function test_the_reference_amount_becomes_the_quote_volume(?string $referenceAmount, string $expected): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['totalAsk' => 737])]);

        $this->provider->getRate($this->usdt, $this->ves, $referenceAmount);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), "/usdt/ves/{$expected}"));
    }

    public function test_total_ask_wins_over_ask_because_it_includes_fees(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['ask' => 730.5, 'totalAsk' => 737.25])]);

        $this->assertSame('737.25', $this->provider->getRate($this->usdt, $this->ves));
    }

    public function test_it_falls_back_to_ask_when_total_ask_is_absent(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['ask' => 730.5, 'bid' => 720])]);

        $this->assertSame('730.5', $this->provider->getRate($this->usdt, $this->ves));
    }

    public function test_the_rate_comes_back_as_a_string_to_preserve_precision(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(['totalAsk' => '737.123456'])]);

        $rate = $this->provider->getRate($this->usdt, $this->ves);

        $this->assertIsString($rate);
        $this->assertSame('737.123456', $rate);
    }

    public function test_an_error_status_is_reported_with_its_code(): void
    {
        Http::fake(['criptoya.com/*' => Http::response(null, 503)]);

        $this->expectException(ExchangeRateUnavailable::class);
        $this->expectExceptionMessageMatches('/503/');

        $this->provider->getRate($this->usdt, $this->ves);
    }

    public function test_a_connection_failure_is_reported_as_an_unavailable_rate(): void
    {
        // The scheduled refresh only catches ExchangeRateUnavailable, so a raw
        // ConnectionException escaping here would abort the whole run.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

        $this->expectException(ExchangeRateUnavailable::class);
        $this->expectExceptionMessageMatches('/No se pudo contactar a CriptoYa/');

        $this->provider->getRate($this->usdt, $this->ves);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function unusablePayloadProvider(): array
    {
        return [
            'empty body' => [[]],
            'zero rate' => [['totalAsk' => 0]],
            'negative rate' => [['totalAsk' => -5]],
            'non numeric rate' => [['totalAsk' => 'n/d']],
            'null rate' => [['totalAsk' => null, 'ask' => null]],
            'unrelated keys only' => [['bid' => 700, 'time' => 123]],
        ];
    }

    #[DataProvider('unusablePayloadProvider')]
    public function test_an_unusable_payload_is_rejected(mixed $payload): void
    {
        Http::fake(['criptoya.com/*' => Http::response($payload)]);

        $this->expectException(ExchangeRateUnavailable::class);
        $this->expectExceptionMessageMatches('/USDT\/VES/');

        $this->provider->getRate($this->usdt, $this->ves);
    }
}
