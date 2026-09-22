<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\PaymentMethodType;
use App\Domain\Payments\Contracts\PaymentProviderInterface;
use App\Domain\Payments\PaymentProviderRegistry;
use App\Models\Currency;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    private PaymentProviderRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new PaymentProviderRegistry;
    }

    public function test_every_payment_method_type_maps_to_a_provider_that_declares_that_same_type(): void
    {
        foreach (PaymentMethodType::cases() as $type) {
            $method = PaymentMethod::factory()->create(['type' => $type]);

            $provider = $this->registry->for($method);

            $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
            $this->assertInstanceOf($type->providerClass(), $provider);
            // Guards against a copy-paste mistake in a new provider class.
            $this->assertSame($type, $provider->type());
        }
    }

    public function test_each_type_maps_to_a_distinct_provider_class(): void
    {
        $classes = array_map(
            fn (PaymentMethodType $type) => $type->providerClass(),
            PaymentMethodType::cases(),
        );

        $this->assertSame($classes, array_unique($classes));
    }

    public function test_the_provider_reports_the_currency_configured_on_its_method(): void
    {
        $ves = Currency::factory()->create(['code' => 'VES']);
        $method = PaymentMethod::factory()->create(['currency_id' => $ves->id]);

        $this->assertTrue($this->registry->for($method)->getCurrency()->is($ves));
    }

    public function test_the_same_type_on_two_methods_yields_independent_providers(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $ves = Currency::factory()->create(['code' => 'VES']);

        // Cash on delivery is explicitly currency-configurable (PRD section 7),
        // so two rows of the same type must not share state.
        $inUsd = PaymentMethod::factory()->efectivo()->create(['currency_id' => $usd->id]);
        $inVes = PaymentMethod::factory()->efectivo()->create(['currency_id' => $ves->id]);

        $this->assertSame('USD', $this->registry->for($inUsd)->getCurrency()->code);
        $this->assertSame('VES', $this->registry->for($inVes)->getCurrency()->code);
    }
}
