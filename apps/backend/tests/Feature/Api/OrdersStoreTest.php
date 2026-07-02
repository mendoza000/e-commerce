<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\ProductVariant;
use App\Models\State;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrdersStoreTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private Currency $ves;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::factory()->create(['code' => 'USD']);
        $this->ves = Currency::factory()->create(['code' => 'VES']);

        $store = StoreSetting::create([
            'store_name' => 'Tienda Test',
            'base_currency_id' => $this->usd->id,
        ]);
        $store->enabledCurrencies()->sync([$this->usd->id, $this->ves->id]);
    }

    /**
     * @return array{state_id: int, municipality_id: int, parish_id: int}
     */
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

    /**
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Juan Perez',
            'customer_phone' => '+584121234567',
            'document_type' => 'V',
            'document_number' => '12345678',
            'address_reference' => 'Cerca de la plaza',
            'payment_currency_id' => $this->usd->id,
        ], $this->makeAddress(), $overrides);
    }

    public function test_guest_can_create_an_order(): void
    {
        $variant = ProductVariant::factory()->create(['price_override' => 10, 'stock' => 10, 'reserved_quantity' => 0]);

        $payload = $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ]);

        $response = $this->postJson('/api/orders', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_payment');
        $response->assertJsonPath('data.base_amount', '20.000000');
        $this->assertStringStartsWith('ORD-', $response->json('data.order_number'));

        $this->assertDatabaseHas('orders', [
            'order_number' => $response->json('data.order_number'),
            'customer_id' => null,
        ]);
    }

    public function test_authenticated_customer_order_via_sanctum_token_sets_customer_id(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('checkout')->plainTextToken;

        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);

        $payload = $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/orders', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('orders', [
            'order_number' => $response->json('data.order_number'),
            'customer_id' => $customer->id,
        ]);
    }

    public function test_exchange_rate_is_frozen_at_creation_and_unaffected_by_later_rates(): void
    {
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'rate' => 40.0,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now()->subMinute(),
            'created_by' => null,
        ]);

        $variant = ProductVariant::factory()->create(['price_override' => 10, 'stock' => 10]);

        $payload = $this->basePayload([
            'payment_currency_id' => $this->ves->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $response = $this->postJson('/api/orders', $payload);
        $response->assertCreated();
        $response->assertJsonPath('data.exchange_rate_applied', '40.000000');
        $response->assertJsonPath('data.payment_amount', '400.000000');

        // A newer rate is inserted after the order exists; re-fetching the same
        // order must still show the originally frozen values.
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'rate' => 999.0,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now(),
            'created_by' => null,
        ]);

        $orderNumber = $response->json('data.order_number');
        $show = $this->getJson("/api/orders/{$orderNumber}?document_number=12345678");

        $show->assertOk();
        $show->assertJsonPath('data.exchange_rate_applied', '40.000000');
        $show->assertJsonPath('data.payment_amount', '400.000000');
    }

    public function test_returns_422_for_insufficient_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 2, 'reserved_quantity' => 0]);

        $payload = $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 5]],
        ]);

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422);
        $response->assertJson(['error' => ['code' => 'validation_error']]);
        $response->assertJsonStructure(['error' => ['fields' => ['items.0.quantity']]]);
    }

    public function test_returns_422_for_inactive_variant(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'is_active' => false]);

        $payload = $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['fields' => ['items.0.product_variant_id']]]);
    }

    public function test_returns_422_for_nonexistent_variant(): void
    {
        $payload = $this->basePayload([
            'items' => [['product_variant_id' => 999999, 'quantity' => 1]],
        ]);

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['fields' => ['items.0.product_variant_id']]]);
    }

    public function test_returns_422_when_municipality_does_not_belong_to_state(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $otherState = State::create(['name' => 'Zulia', 'code' => 'ZU']);

        $payload = $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]);
        $payload['state_id'] = $otherState->id;

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error' => ['fields' => ['municipality_id']]]);
    }

    public function test_order_number_follows_expected_format_and_is_unique(): void
    {
        $variantA = ProductVariant::factory()->create(['stock' => 10]);
        $variantB = ProductVariant::factory()->create(['stock' => 10]);

        $responseA = $this->postJson('/api/orders', $this->basePayload([
            'items' => [['product_variant_id' => $variantA->id, 'quantity' => 1]],
        ]));
        $responseB = $this->postJson('/api/orders', $this->basePayload([
            'items' => [['product_variant_id' => $variantB->id, 'quantity' => 1]],
        ]));

        $responseA->assertCreated();
        $responseB->assertCreated();

        $numberA = $responseA->json('data.order_number');
        $numberB = $responseB->json('data.order_number');

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $numberA);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $numberB);
        $this->assertNotSame($numberA, $numberB);
    }

    public function test_reservation_expires_at_uses_configured_reservation_minutes(): void
    {
        Carbon::setTestNow('2026-07-02 10:00:00');

        config(['commerce.reservation_minutes' => 45]);

        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $response = $this->postJson('/api/orders', $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('data.reservation_expires_at', '2026-07-02T10:45:00.000000Z');

        Carbon::setTestNow();
    }

    /**
     * This is a SEQUENTIAL smoke test: it proves the logical correctness of the
     * locking/validation flow (last unit gets reserved once, the second attempt
     * is correctly rejected) using two consecutive requests in the same
     * process. It does NOT exercise true multi-connection concurrency (two
     * transactions racing against the same row from separate DB connections).
     * Real concurrency hardening under concurrent connections is out of scope
     * for this phase and is deferred to later hardening work.
     */
    public function test_sequential_double_reservation_on_last_unit_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 1, 'reserved_quantity' => 0]);

        $first = $this->postJson('/api/orders', $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));
        $first->assertCreated();

        $second = $this->postJson('/api/orders', $this->basePayload([
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));
        $second->assertStatus(422);
        $second->assertJsonStructure(['error' => ['fields' => ['items.0.quantity']]]);

        $this->assertSame(1, $variant->fresh()->reserved_quantity);
    }
}
