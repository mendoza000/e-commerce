<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\State;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersShowTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private PaymentMethod $usdMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::factory()->create(['code' => 'USD']);

        $store = StoreSetting::create([
            'store_name' => 'Tienda Test',
            'base_currency_id' => $this->usd->id,
        ]);
        $store->enabledCurrencies()->sync([$this->usd->id]);

        $this->usdMethod = PaymentMethod::factory()->zelle()->create(['currency_id' => $this->usd->id]);
    }

    private function makeAddress(): array
    {
        $state = State::create(['name' => 'Miranda', 'code' => 'MI']);
        $municipality = Municipality::create(['state_id' => $state->id, 'name' => 'Sucre']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'name' => 'Petare']);

        return [
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
        ];
    }

    private function createOrder(?Customer $customer = null): array
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);

        $payload = array_merge([
            'customer_name' => 'Juan Perez',
            'customer_phone' => '+584121234567',
            'document_type' => 'V',
            'document_number' => '12345678',
            'address_reference' => 'Cerca de la plaza',
            'payment_method_id' => $this->usdMethod->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ], $this->makeAddress());

        if ($customer !== null) {
            $token = $customer->createToken('checkout')->plainTextToken;
            $request = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/orders', $payload);
        } else {
            $request = $this->postJson('/api/orders', $payload);
        }

        $request->assertCreated();

        return $request->json('data');
    }

    public function test_owner_via_sanctum_token_sees_full_order(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->createOrder($customer);

        $token = $customer->createToken('lookup')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/orders/{$order['order_number']}");

        $response->assertOk();
        $response->assertJsonPath('data.order_number', $order['order_number']);
        $response->assertJsonPath('data.document_number', '12345678');
    }

    public function test_guest_with_correct_document_number_sees_full_order(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson("/api/orders/{$order['order_number']}?document_number=12345678");

        $response->assertOk();
        $response->assertJsonPath('data.order_number', $order['order_number']);
    }

    public function test_wrong_document_number_returns_404(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson("/api/orders/{$order['order_number']}?document_number=00000000");

        $response->assertStatus(404);
        $response->assertJson(['error' => ['code' => 'not_found']]);
    }

    public function test_missing_document_number_returns_404(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson("/api/orders/{$order['order_number']}");

        $response->assertStatus(404);
    }

    public function test_unknown_order_number_returns_404(): void
    {
        $response = $this->getJson('/api/orders/ORD-99999999-ZZZZZZ');

        $response->assertStatus(404);
    }

    public function test_response_body_never_contains_numeric_id_or_customer_id(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson("/api/orders/{$order['order_number']}?document_number=12345678");

        $response->assertOk();

        // The top-level order object must not leak its numeric primary key or
        // customer_id. Nested objects (address.state/municipality/parish,
        // base_currency/payment_currency) are allowed to expose their own ids
        // per the OrderResource contract.
        $data = $response->json('data');
        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('customer_id', $data);
    }
}
