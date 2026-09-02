<?php

namespace App\Http\Resources\Admin;

use App\Domain\Enums\OrderStatus;
use App\Http\Resources\OrderItemResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The order as the panel needs to see it: everything the storefront resource
 * withholds — who changed what, every proof, the reservation clock — plus the
 * actions the current status allows, so the UI never has to reimplement the
 * state machine to decide which buttons to draw.
 *
 * The listing and the detail view share this class; what separates them is
 * what the controller eager loads, not a second resource.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // As on the storefront, order_number is the identifier and the
            // numeric id stays inside the database.
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'document_type' => $this->document_type?->value,
                'document_number' => $this->document_number,
                // Guest checkout is the norm, so the panel has to be able to
                // tell an account apart from a one-off buyer.
                'is_registered' => $this->customer_id !== null,
            ],
            'address' => [
                'state' => $this->whenLoaded('state', fn () => $this->state?->name),
                'municipality' => $this->whenLoaded('municipality', fn () => $this->municipality?->name),
                'parish' => $this->whenLoaded('parish', fn () => $this->parish?->name),
                'reference' => $this->address_reference,
            ],
            'base_currency' => $this->whenLoaded('baseCurrency', fn () => [
                'code' => $this->baseCurrency->code,
                'symbol' => $this->baseCurrency->symbol,
            ]),
            'payment_currency' => $this->whenLoaded('paymentCurrency', fn () => [
                'code' => $this->paymentCurrency->code,
                'symbol' => $this->paymentCurrency->symbol,
            ]),
            'base_amount' => $this->base_amount,
            // The rate frozen at checkout, shown next to both amounts so an
            // admin can see what the customer was actually asked to pay.
            'exchange_rate_applied' => $this->exchange_rate_applied,
            'payment_amount' => $this->payment_amount,
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod ? [
                'id' => $this->paymentMethod->id,
                'type' => $this->paymentMethod->type->value,
                'label' => $this->paymentMethod->label,
            ] : null),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'payment_proofs' => PaymentProofResource::collection($this->whenLoaded('paymentProofs')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'reservation_expires_at' => $this->reservation_expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'actions' => $this->actions(),
        ];
    }

    /**
     * What can be done to this order right now, derived from the state machine
     * instead of restated. The three flags map to the endpoints that carry a
     * side effect; `available_transitions` covers the plain fulfilment moves.
     *
     * A UI hint, exactly like UserResource::permissions: the backend refuses an
     * illegal move regardless of what the panel drew.
     *
     * @return array<string, mixed>
     */
    private function actions(): array
    {
        return [
            'can_confirm_payment' => $this->status->canTransitionTo(OrderStatus::Paid),
            'can_reject_payment' => $this->status->canTransitionTo(OrderStatus::PendingPayment),
            'can_cancel' => $this->status->canTransitionTo(OrderStatus::Cancelled),
            'available_transitions' => array_map(
                fn (OrderStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                $this->status->fulfillmentTransitions(),
            ),
        ];
    }
}
