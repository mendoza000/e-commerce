<?php

namespace Tests\Unit\Domain;

use App\Domain\Enums\PaymentMethodType;
use App\Domain\Payments\Providers\EfectivoContraEntregaProvider;
use App\Domain\Payments\Providers\PagoMovilProvider;
use App\Domain\Payments\Providers\ZelleProvider;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_type_resolves_to_its_own_provider(): void
    {
        foreach (PaymentMethodType::cases() as $type) {
            $method = PaymentMethod::factory()->create(['type' => $type]);

            $this->assertInstanceOf($type->providerClass(), $method->provider());
            $this->assertSame($type, $method->provider()->type());
        }
    }

    public function test_pago_movil_instructions_carry_the_account_and_the_converted_amount(): void
    {
        $ves = Currency::factory()->create(['code' => 'VES']);
        $method = PaymentMethod::factory()->create(['currency_id' => $ves->id]);
        $order = Order::factory()->create([
            'payment_currency_id' => $ves->id,
            'payment_amount' => 7370,
        ]);

        $instructions = $method->instructionsFor($order);

        $this->assertInstanceOf(PagoMovilProvider::class, $method->provider());
        $this->assertSame('VES', $instructions['currency']);
        $this->assertSame('7370.000000', $instructions['amount']);
        $this->assertSame('0102', $instructions['account']['bank_code']);
        $this->assertTrue($instructions['requires_proof']);
    }

    public function test_a_provider_only_exposes_the_account_keys_it_knows_about(): void
    {
        $method = PaymentMethod::factory()->zelle()->create();
        $method->instructions = [
            'email' => 'pagos@tienda.test',
            'holder_name' => 'Tienda Demo',
            // Left over from a previous configuration; must not reach the API.
            'internal_note' => 'no mostrar',
        ];
        $method->save();

        $instructions = $method->instructionsFor(Order::factory()->create());

        $this->assertInstanceOf(ZelleProvider::class, $method->provider());
        $this->assertSame(['email', 'holder_name'], array_keys($instructions['account']));
    }

    public function test_cash_on_delivery_needs_no_proof(): void
    {
        $method = PaymentMethod::factory()->efectivo()->create();

        $this->assertInstanceOf(EfectivoContraEntregaProvider::class, $method->provider());
        $this->assertFalse($method->requiresProof());
    }

    public function test_missing_instruction_keys_come_back_as_null_instead_of_failing(): void
    {
        $method = PaymentMethod::factory()->create(['instructions' => []]);

        $this->assertNull($method->instructionValue('bank'));
        $this->assertNull($method->instructionsFor(Order::factory()->create())['account']['bank']);
    }
}
