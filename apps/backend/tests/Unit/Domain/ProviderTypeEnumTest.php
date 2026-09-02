<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\ExchangeRateProviderType;
use App\Domain\Enums\PaymentMethodType;
use App\Domain\ExchangeRates\Contracts\ExchangeRateProviderInterface;
use App\Domain\Payments\Contracts\PaymentProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * These enums are the single source of truth for type => provider class.
 * If a case is added without its class, this is what catches it.
 */
class ProviderTypeEnumTest extends TestCase
{
    public function test_every_payment_type_points_at_an_existing_provider_class(): void
    {
        foreach (PaymentMethodType::cases() as $type) {
            $class = $type->providerClass();

            $this->assertTrue(class_exists($class), "{$class} does not exist.");
            $this->assertTrue(
                is_subclass_of($class, PaymentProviderInterface::class),
                "{$class} does not implement PaymentProviderInterface."
            );
        }
    }

    public function test_every_exchange_rate_type_points_at_an_existing_provider_class(): void
    {
        foreach (ExchangeRateProviderType::cases() as $type) {
            $class = $type->providerClass();

            $this->assertTrue(class_exists($class), "{$class} does not exist.");
            $this->assertTrue(
                is_subclass_of($class, ExchangeRateProviderInterface::class),
                "{$class} does not implement ExchangeRateProviderInterface."
            );
        }
    }

    public function test_payment_type_values_are_the_stable_strings_stored_in_the_database(): void
    {
        // Changing one of these silently orphans existing payment_methods rows.
        $this->assertSame(
            ['pago_movil', 'zelle', 'transferencia_nacional', 'efectivo_contra_entrega'],
            array_column(PaymentMethodType::cases(), 'value')
        );
    }

    public function test_exchange_rate_type_values_are_stable(): void
    {
        $this->assertSame(
            ['manual', 'criptoya'],
            array_column(ExchangeRateProviderType::cases(), 'value')
        );
    }

    public function test_every_payment_type_has_a_human_label(): void
    {
        foreach (PaymentMethodType::cases() as $type) {
            $this->assertNotSame('', $type->label());
            $this->assertNotSame($type->value, $type->label());
        }
    }
}
