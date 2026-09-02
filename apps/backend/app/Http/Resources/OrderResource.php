<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // The numeric id is never exposed: order_number is the only public identifier.
            'order_number' => $this->order_number,
            'status' => $this->status,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'address' => [
                'state' => $this->whenLoaded('state', fn () => $this->state ? [
                    'id' => $this->state->id,
                    'name' => $this->state->name,
                ] : null),
                'municipality' => $this->whenLoaded('municipality', fn () => $this->municipality ? [
                    'id' => $this->municipality->id,
                    'name' => $this->municipality->name,
                ] : null),
                'parish' => $this->whenLoaded('parish', fn () => $this->parish ? [
                    'id' => $this->parish->id,
                    'name' => $this->parish->name,
                ] : null),
                'address_reference' => $this->address_reference,
            ],
            'base_currency' => $this->whenLoaded('baseCurrency', fn () => [
                'id' => $this->baseCurrency->id,
                'code' => $this->baseCurrency->code,
                'symbol' => $this->baseCurrency->symbol,
            ]),
            'payment_currency' => $this->whenLoaded('paymentCurrency', fn () => [
                'id' => $this->paymentCurrency->id,
                'code' => $this->paymentCurrency->code,
                'symbol' => $this->paymentCurrency->symbol,
            ]),
            'base_amount' => $this->base_amount,
            'exchange_rate_applied' => $this->exchange_rate_applied,
            'payment_amount' => $this->payment_amount,
            'payment_method' => PaymentMethodResource::make($this->whenLoaded('paymentMethod')),
            // Everything the customer needs to actually pay: account details
            // plus the amount already converted to this method's currency.
            'payment_instructions' => $this->whenLoaded('paymentMethod', fn () => $this->paymentInstructions()),
            'payment_proof' => PaymentProofResource::make($this->whenLoaded('latestPaymentProof')),
            'fulfillment_method' => $this->whenLoaded('fulfillmentMethod', fn () => $this->fulfillmentMethod ? [
                'id' => $this->fulfillmentMethod->id,
                'type' => $this->fulfillmentMethod->type->value,
                'label' => $this->fulfillmentMethod->label,
            ] : null),
            // Frozen in base currency at checkout, same as base_amount. Null
            // means "a coordinar": the store had no rate for this destination.
            'shipping_amount' => $this->shipping_amount,
            'shipping' => [
                'courier' => $this->courier,
                'tracking_code' => $this->tracking_code,
                'note' => $this->shipping_note,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'reservation_expires_at' => $this->reservation_expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
