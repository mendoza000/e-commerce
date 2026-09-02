<?php

namespace Tests\Unit\Models;

use App\Domain\Enums\PaymentMethodType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_type_column_is_cast_to_the_enum(): void
    {
        $method = PaymentMethod::factory()->zelle()->create();

        $this->assertSame(PaymentMethodType::Zelle, $method->fresh()->type);
    }

    public function test_the_instructions_column_is_cast_to_an_array(): void
    {
        $method = PaymentMethod::factory()->create(['instructions' => ['bank' => 'Banesco']]);

        $this->assertSame(['bank' => 'Banesco'], $method->fresh()->instructions);
    }

    public function test_it_belongs_to_a_currency(): void
    {
        $ves = Currency::factory()->create(['code' => 'VES']);
        $method = PaymentMethod::factory()->create(['currency_id' => $ves->id]);

        $this->assertTrue($method->currency->is($ves));
    }

    public function test_it_has_the_orders_paid_with_it(): void
    {
        $method = PaymentMethod::factory()->create();
        Order::factory()->count(2)->create(['payment_method_id' => $method->id]);
        Order::factory()->create();

        $this->assertSame(2, $method->orders()->count());
    }

    public function test_the_active_scope_hides_disabled_methods(): void
    {
        PaymentMethod::factory()->create(['label' => 'Visible']);
        PaymentMethod::factory()->inactive()->create(['label' => 'Oculto']);

        $labels = PaymentMethod::query()->active()->pluck('label')->all();

        $this->assertSame(['Visible'], $labels);
    }

    public function test_the_active_scope_orders_by_position_then_id(): void
    {
        $third = PaymentMethod::factory()->create(['label' => 'Tercero', 'position' => 5]);
        $first = PaymentMethod::factory()->create(['label' => 'Primero', 'position' => 1]);
        // Same position as $first: id decides, so the order stays stable.
        $second = PaymentMethod::factory()->create(['label' => 'Segundo', 'position' => 1]);

        $ordered = PaymentMethod::query()->active()->pluck('id')->all();

        $this->assertSame([$first->id, $second->id, $third->id], $ordered);
    }

    public function test_instruction_value_reads_a_key_from_the_json_blob(): void
    {
        $method = PaymentMethod::factory()->create(['instructions' => ['bank_code' => '0102']]);

        $this->assertSame('0102', $method->instructionValue('bank_code'));
    }

    public function test_instruction_value_returns_null_instead_of_failing_on_a_missing_key(): void
    {
        $method = PaymentMethod::factory()->create(['instructions' => []]);

        $this->assertNull($method->instructionValue('bank'));
    }

    public function test_instruction_value_survives_a_null_instructions_column(): void
    {
        $method = PaymentMethod::factory()->create(['instructions' => null]);

        $this->assertNull($method->instructionValue('bank'));
    }

    public function test_instruction_value_always_returns_a_string(): void
    {
        // An admin may well type a phone number as a JSON number.
        $method = PaymentMethod::factory()->create(['instructions' => ['phone' => 4121234567]]);

        $this->assertSame('4121234567', $method->instructionValue('phone'));
    }

    public function test_requires_proof_is_delegated_to_the_provider(): void
    {
        $this->assertTrue(PaymentMethod::factory()->create()->requiresProof());
        $this->assertFalse(PaymentMethod::factory()->efectivo()->create()->requiresProof());
    }

    public function test_instructions_for_an_order_carry_that_order_s_amount(): void
    {
        $ves = Currency::factory()->create(['code' => 'VES']);
        $method = PaymentMethod::factory()->create(['currency_id' => $ves->id]);

        $cheap = Order::factory()->create(['payment_amount' => 100]);
        $pricey = Order::factory()->create(['payment_amount' => 250000]);

        $this->assertSame('100.000000', $method->instructionsFor($cheap)['amount']);
        $this->assertSame('250000.000000', $method->instructionsFor($pricey)['amount']);
    }
}
