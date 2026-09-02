<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingFromAdminPanel();
    }

    private function order(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'payment_method_id' => PaymentMethod::factory()->create()->id,
        ], $overrides));
    }

    public function test_the_listing_requires_a_session(): void
    {
        $this->getJson('/api/admin/orders')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_a_deactivated_admin_is_turned_away(): void
    {
        $this->actingAs(User::factory()->inactive()->create())
            ->getJson('/api/admin/orders')
            ->assertUnauthorized();
    }

    public function test_staff_can_list_orders(): void
    {
        $this->order();
        $this->order();

        $this->actingAs(User::factory()->staff()->create())
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_the_listing_shows_the_newest_order_first(): void
    {
        $first = $this->order();
        $latest = $this->order();

        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.order_number', $latest->order_number)
            ->assertJsonPath('data.1.order_number', $first->order_number);
    }

    public function test_the_listing_counts_items_without_shipping_them_all(): void
    {
        $order = $this->order();
        OrderItem::factory()->count(3)->create(['order_id' => $order->id]);

        $response = $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items_count', 3);

        // The list is a queue, not a detail view: the lines themselves and the
        // audit trail stay out of it.
        $response->assertJsonMissingPath('data.0.items');
        $response->assertJsonMissingPath('data.0.status_history');
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        $this->order(['status' => OrderStatus::PendingPayment]);
        $submitted = $this->order(['status' => OrderStatus::PaymentSubmitted]);

        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/orders?status=payment_submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $submitted->order_number);
    }

    public function test_an_unknown_status_is_a_validation_error(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/orders?status=inventado')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_orders_can_be_searched_by_number_name_or_document(): void
    {
        $target = $this->order([
            'order_number' => 'ORD-777777',
            'customer_name' => 'Maria Perez',
            'document_number' => '19283746',
        ]);
        $this->order(['order_number' => 'ORD-111111', 'customer_name' => 'Otro Cliente']);

        $admin = User::factory()->owner()->create();

        foreach (['777777', 'maria', '19283746'] as $term) {
            $this->actingAs($admin)
                ->getJson('/api/admin/orders?search='.$term)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.order_number', $target->order_number);
        }
    }

    public function test_search_treats_wildcards_as_literal_characters(): void
    {
        $this->order(['customer_name' => 'Maria Perez']);
        $this->order(['customer_name' => 'Otro Cliente']);

        // Unescaped, `_` matches any single character in ILIKE and this would
        // return the whole store.
        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/orders?search=_')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_listing_is_paginated(): void
    {
        Order::factory()->count(3)->create();

        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/orders?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_the_detail_carries_items_history_proofs_and_the_frozen_rate(): void
    {
        $order = $this->order(['exchange_rate_applied' => '36.500000']);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Camisa azul']);
        PaymentProof::factory()->create(['order_id' => $order->id, 'reference' => '0011223344']);
        $order->transitionTo(OrderStatus::PaymentSubmitted, null, 'El cliente envio el comprobante.');

        $response = $this->actingAs(User::factory()->owner()->create())
            ->getJson("/api/admin/orders/{$order->order_number}")
            ->assertOk();

        $response->assertJsonPath('data.status', 'payment_submitted');
        $response->assertJsonPath('data.status_label', 'Comprobante enviado');
        $response->assertJsonPath('data.exchange_rate_applied', '36.500000');
        $response->assertJsonPath('data.items.0.product_name', 'Camisa azul');
        $response->assertJsonPath('data.payment_proofs.0.reference', '0011223344');
        $response->assertJsonPath('data.status_history.0.to_status', 'payment_submitted');
        $response->assertJsonPath('data.status_history.0.to_status_label', 'Comprobante enviado');
        // Nobody clicked it: the customer uploaded the proof.
        $response->assertJsonPath('data.status_history.0.changed_by', null);
    }

    public function test_the_detail_tells_the_panel_which_actions_are_available(): void
    {
        $order = $this->order(['status' => OrderStatus::PaymentSubmitted]);

        $response = $this->actingAs(User::factory()->owner()->create())
            ->getJson("/api/admin/orders/{$order->order_number}")
            ->assertOk();

        $response->assertJsonPath('data.actions.can_confirm_payment', true);
        $response->assertJsonPath('data.actions.can_reject_payment', true);
        $response->assertJsonPath('data.actions.can_cancel', true);
        // Fulfilment moves only open up once the order is paid.
        $response->assertJsonCount(0, 'data.actions.available_transitions');
    }

    public function test_a_paid_order_offers_preparing_as_its_next_move(): void
    {
        $order = $this->order(['status' => OrderStatus::Paid]);

        $this->actingAs(User::factory()->owner()->create())
            ->getJson("/api/admin/orders/{$order->order_number}")
            ->assertOk()
            ->assertJsonPath('data.actions.can_confirm_payment', false)
            ->assertJsonPath('data.actions.available_transitions.0.value', 'preparing')
            ->assertJsonPath('data.actions.available_transitions.0.label', OrderStatus::Preparing->label());
    }

    public function test_an_unknown_order_number_is_a_404(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->getJson('/api/admin/orders/ORD-000000')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }
}
