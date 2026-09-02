<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/my-orders')->assertStatus(401);
    }

    public function test_it_lists_only_the_authenticated_customers_own_orders(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();

        Order::factory()->count(2)->create(['customer_id' => $customer->id]);
        Order::factory()->create(['customer_id' => $other->id]);

        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/my-orders');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_orders_are_listed_newest_first(): void
    {
        $customer = Customer::factory()->create();

        $older = Order::factory()->create(['customer_id' => $customer->id, 'order_number' => 'ORD-1']);
        $newer = Order::factory()->create(['customer_id' => $customer->id, 'order_number' => 'ORD-2']);

        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/my-orders');

        $response->assertOk()
            ->assertJsonPath('data.0.order_number', $newer->order_number)
            ->assertJsonPath('data.1.order_number', $older->order_number);
    }

    public function test_a_guest_customer_with_no_orders_sees_an_empty_list(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => null]);

        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/my-orders');

        $response->assertOk()->assertJsonCount(0, 'data');
    }
}
