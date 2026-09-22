<?php

namespace Tests\Unit\Services;

use App\Domain\Enums\DocumentType;
use App\Domain\Enums\OrderStatus;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\State;
use App\Models\StoreSetting;
use App\Services\ExchangeRateService;
use App\Services\InventoryReservationService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    private Currency $usd;

    private Currency $ves;

    private PaymentMethod $usdMethod;

    private PaymentMethod $vesMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderService(new InventoryReservationService, new ExchangeRateService);

        $this->usd = Currency::factory()->create(['code' => 'USD']);
        $this->ves = Currency::factory()->create(['code' => 'VES']);

        $store = StoreSetting::create([
            'store_name' => 'Tienda Test',
            'base_currency_id' => $this->usd->id,
        ]);
        $store->enabledCurrencies()->sync([$this->usd->id, $this->ves->id]);

        // The order's payment currency comes from the chosen method, so the
        // tests pick a method rather than a currency.
        $this->usdMethod = PaymentMethod::factory()->zelle()->create(['currency_id' => $this->usd->id]);
        $this->vesMethod = PaymentMethod::factory()->create(['currency_id' => $this->ves->id]);
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

    private function baseValidated(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Juan Perez',
            'customer_phone' => '+58'.'4121234567',
            'document_type' => DocumentType::Cedula,
            'document_number' => '12345678',
            'address_reference' => 'Cerca de la plaza',
            'payment_method_id' => $this->usdMethod->id,
        ], $overrides);
    }

    public function test_creates_a_guest_order_with_frozen_totals(): void
    {
        $product = Product::factory()->create(['name' => 'Camisa']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price_override' => 10,
            'stock' => 10,
            'reserved_quantity' => 0,
        ]);

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 3],
            ],
        ]));

        $order = $this->service->createOrder($validated, null);

        $this->assertNull($order->customer_id);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('30.000000', $order->base_amount);
        $this->assertSame('1.000000', $order->exchange_rate_applied);
        $this->assertSame('30.000000', $order->payment_amount);
        $this->assertCount(1, $order->items);
        $this->assertSame('Camisa', $order->items->first()->product_name);
        $this->assertSame($variant->sku, $order->items->first()->sku);
    }

    public function test_sets_customer_id_when_an_authenticated_customer_is_passed(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['price_override' => 5, 'stock' => 10]);

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $order = $this->service->createOrder($validated, $customer);

        $this->assertSame($customer->id, $order->customer_id);
    }

    public function test_freezes_exchange_rate_at_creation_time(): void
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

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'payment_method_id' => $this->vesMethod->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $order = $this->service->createOrder($validated, null);

        $this->assertSame('40.000000', $order->exchange_rate_applied);
        $this->assertSame('400.000000', $order->payment_amount);

        // A newer rate arrives after the order was placed; the order's frozen
        // values must not change.
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->ves->id,
            'rate' => 999.0,
            'source' => 'manual',
            'reference_amount' => null,
            'effective_at' => now(),
            'created_by' => null,
        ]);

        $order->refresh();
        $this->assertSame('40.000000', $order->exchange_rate_applied);
        $this->assertSame('400.000000', $order->payment_amount);
    }

    public function test_throws_422_when_no_exchange_rate_exists_for_payment_currency(): void
    {
        $variant = ProductVariant::factory()->create(['price_override' => 10, 'stock' => 10]);

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'payment_method_id' => $this->vesMethod->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $this->expectException(ValidationException::class);

        try {
            $this->service->createOrder($validated, null);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method_id', $e->errors());

            throw $e;
        }
    }

    public function test_reserves_stock_for_every_item(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10, 'reserved_quantity' => 0]);

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 4]],
        ]));

        $this->service->createOrder($validated, null);

        $this->assertSame(4, $variant->fresh()->reserved_quantity);
    }

    public function test_generates_order_number_matching_expected_format(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $order = $this->service->createOrder($validated, null);

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $order->order_number);
    }

    public function test_sets_reservation_expiry_using_configured_minutes(): void
    {
        Carbon::setTestNow('2026-07-02 10:00:00');

        config(['commerce.reservation_minutes' => 30]);

        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $order = $this->service->createOrder($validated, null);

        $this->assertTrue($order->reservation_expires_at->equalTo(now()->addMinutes(30)));

        Carbon::setTestNow();
    }

    public function test_writes_initial_pending_payment_status_history(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);

        $validated = $this->baseValidated(array_merge($this->makeAddress(), [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $order = $this->service->createOrder($validated, null);

        $history = $order->statusHistory()->firstOrFail();
        $this->assertNull($history->from_status);
        $this->assertSame('pending_payment', $history->to_status);
    }
}
