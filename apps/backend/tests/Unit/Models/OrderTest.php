<?php

namespace Tests\Unit\Models;

use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_resolves_distinct_base_and_payment_currencies_and_its_items(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $ves = Currency::factory()->create(['code' => 'VES']);

        $order = Order::factory()->create([
            'base_currency_id' => $usd->id,
            'payment_currency_id' => $ves->id,
        ]);

        OrderItem::factory()->count(2)->create([
            'order_id' => $order->id,
        ]);

        $this->assertSame('USD', $order->baseCurrency->code);
        $this->assertSame('VES', $order->paymentCurrency->code);
        $this->assertNotSame($order->baseCurrency->code, $order->paymentCurrency->code);

        $this->assertCount(2, $order->items);
    }
}
