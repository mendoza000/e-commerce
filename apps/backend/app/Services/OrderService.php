<?php

namespace App\Services;

use App\Domain\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\FulfillmentMethod;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\State;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly InventoryReservationService $reservations,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * Creates an order from a validated checkout payload, locking and
     * reserving stock, freezing the exchange rate, and snapshotting every
     * customer/price detail so the order never depends on data that could
     * change later. Runs entirely inside one transaction.
     *
     * @param  array<string, mixed>  $validated  The validated OrderStoreRequest payload.
     * @param  Customer|null  $customer  The authenticated customer, if any. Null means guest checkout.
     */
    public function createOrder(array $validated, ?Customer $customer): Order
    {
        return DB::transaction(function () use ($validated, $customer) {
            $quantitiesByVariantId = [];

            foreach ($validated['items'] as $item) {
                $quantitiesByVariantId[$item['product_variant_id']] = $item['quantity'];
            }

            $lockedVariants = $this->reservations
                ->lockVariantsForOrder($quantitiesByVariantId)
                ->load(['product', 'optionValues']);

            // Base currency is always resolved server-side, never trusted from the client.
            $store = StoreSetting::current();
            $baseCurrency = $store->baseCurrency;

            // The client picks a payment *method*; its currency is what the
            // order gets frozen in, so the two can never disagree.
            $paymentMethod = PaymentMethod::query()->with('currency')->findOrFail($validated['payment_method_id']);
            $paymentCurrency = $paymentMethod->currency;

            $baseAmount = '0';

            foreach ($validated['items'] as $item) {
                $variant = $lockedVariants->get($item['product_variant_id']);
                $lineTotal = bcmul($variant->effectivePrice(), (string) $item['quantity'], 6);
                $baseAmount = bcadd($baseAmount, $lineTotal, 6);
            }

            [$fulfillmentMethod, $shippingAmount] = $this->resolveShipping($validated);

            // "A coordinar" (no zone rate configured) freezes as null and adds
            // nothing to the total the customer is asked to pay now — the PRD
            // is explicit that this must never block checkout.
            $orderTotalBase = bcadd($baseAmount, $shippingAmount ?? '0', 6);

            if ($paymentCurrency->is($baseCurrency)) {
                $exchangeRateApplied = '1.000000';
            } else {
                $rate = $this->exchangeRates->latestRate($baseCurrency, $paymentCurrency);

                if ($rate === null) {
                    throw ValidationException::withMessages([
                        'payment_method_id' => ['No hay una tasa de cambio disponible para la moneda de este método de pago.'],
                    ]);
                }

                $exchangeRateApplied = (string) $rate->rate;
            }

            $paymentAmount = bcmul($orderTotalBase, $exchangeRateApplied, 6);

            $order = Order::create([
                'customer_id' => $customer?->id,
                'status' => OrderStatus::PendingPayment,
                'order_number' => $this->generateUniqueOrderNumber(),
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'document_type' => $validated['document_type'],
                'document_number' => $validated['document_number'],
                'state_id' => $validated['state_id'],
                'municipality_id' => $validated['municipality_id'],
                'parish_id' => $validated['parish_id'],
                'address_reference' => $validated['address_reference'],
                'base_currency_id' => $baseCurrency->id,
                'base_amount' => $baseAmount,
                'payment_currency_id' => $paymentCurrency->id,
                'exchange_rate_applied' => $exchangeRateApplied,
                'payment_amount' => $paymentAmount,
                'payment_method_id' => $paymentMethod->id,
                'fulfillment_method_id' => $fulfillmentMethod?->id,
                'shipping_amount' => $shippingAmount,
                'reservation_expires_at' => now()->addMinutes((int) config('commerce.reservation_minutes')),
            ]);

            foreach ($validated['items'] as $item) {
                $variant = $lockedVariants->get($item['product_variant_id']);
                $unitPrice = $variant->effectivePrice();
                $subtotal = bcmul($unitPrice, (string) $item['quantity'], 6);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    // Never trust client-supplied names/prices: everything here is
                    // derived from the variant locked in lockVariantsForOrder above.
                    'product_name' => $variant->product->name,
                    'variant_description' => $this->describeVariant($variant),
                    'sku' => $variant->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ]);
            }

            $this->reservations->reserve($order, $lockedVariants, $quantitiesByVariantId);

            // Opening entry of the audit trail. Every later change goes through
            // Order::transitionTo, which writes its own history row.
            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment->value,
                'changed_by' => null,
                'reason' => null,
            ]);

            return $order->load([
                'items', 'baseCurrency', 'paymentCurrency', 'state', 'municipality', 'parish',
                'paymentMethod.currency', 'fulfillmentMethod.currency',
            ]);
        });
    }

    /**
     * Resolves the chosen fulfillment method (if any) and prices it for the
     * order's destination. Returns null cost for "a coordinar" — no zone rate
     * configured — which is a valid outcome, not an error: PRD section 6 never
     * blocks checkout on a missing shipping quote.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: ?FulfillmentMethod, 1: ?string}
     */
    private function resolveShipping(array $validated): array
    {
        if (empty($validated['fulfillment_method_id'])) {
            return [null, null];
        }

        $method = FulfillmentMethod::query()->findOrFail($validated['fulfillment_method_id']);

        $state = State::query()->find($validated['state_id']);
        $municipality = Municipality::query()->find($validated['municipality_id']);

        return [$method, $method->estimateCostFor($state, $municipality)];
    }

    private function describeVariant(ProductVariant $variant): ?string
    {
        if ($variant->optionValues->isEmpty()) {
            return null;
        }

        return $variant->optionValues->pluck('value')->implode(' / ');
    }

    private function generateUniqueOrderNumber(): string
    {
        do {
            $candidate = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
