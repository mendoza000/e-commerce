<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_active_methods_with_their_currency(): void
    {
        $ves = Currency::factory()->create(['code' => 'VES', 'symbol' => 'Bs.']);
        PaymentMethod::factory()->create(['currency_id' => $ves->id, 'label' => 'Pago Móvil']);

        $response = $this->getJson('/api/payment-methods');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'pago_movil');
        $response->assertJsonPath('data.0.label', 'Pago Móvil');
        $response->assertJsonPath('data.0.currency.code', 'VES');
        $response->assertJsonPath('data.0.requires_proof', true);
    }

    public function test_inactive_methods_are_never_offered(): void
    {
        PaymentMethod::factory()->create(['label' => 'Activo']);
        PaymentMethod::factory()->inactive()->create(['label' => 'Deshabilitado']);

        $response = $this->getJson('/api/payment-methods');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.label', 'Activo');
    }

    public function test_methods_come_back_in_the_order_the_admin_arranged(): void
    {
        PaymentMethod::factory()->zelle()->create(['label' => 'Segundo', 'position' => 2]);
        PaymentMethod::factory()->create(['label' => 'Primero', 'position' => 1]);

        $response = $this->getJson('/api/payment-methods');

        $response->assertOk();
        $this->assertSame(['Primero', 'Segundo'], array_column($response->json('data'), 'label'));
    }

    public function test_cash_on_delivery_is_reported_as_not_needing_a_proof(): void
    {
        PaymentMethod::factory()->efectivo()->create();

        $response = $this->getJson('/api/payment-methods');

        $response->assertOk();
        $response->assertJsonPath('data.0.requires_proof', false);
    }
}
