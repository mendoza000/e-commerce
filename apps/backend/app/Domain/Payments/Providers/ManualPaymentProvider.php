<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Payments\Contracts\PaymentProviderInterface;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Shared behaviour of every manual (non-automated) payment method: the account
 * details come from `payment_methods.instructions`, the amount comes from the
 * order's already-frozen `payment_amount`, and confirming is a human decision
 * with no external call.
 *
 * Subclasses only declare which type they are; which fields of the JSON blob
 * that type exposes is declared once on PaymentMethodType, so the admin form
 * and the customer-facing payload can never drift apart.
 */
abstract class ManualPaymentProvider implements PaymentProviderInterface
{
    public function __construct(protected readonly PaymentMethod $method) {}

    public function getCurrency(): Currency
    {
        return $this->method->currency;
    }

    public function requiresProof(): bool
    {
        return true;
    }

    public function confirm(Order $order, User $admin): void
    {
        // Nothing to call: a manual method is confirmed by the admin having
        // looked at the proof. Kept explicit so the seam is visible.
    }

    /**
     * @return array<string, mixed>
     */
    public function getInstructions(Order $order): array
    {
        return [
            'type' => $this->type()->value,
            'label' => $this->method->label,
            'currency' => $this->getCurrency()->code,
            'amount' => $order->payment_amount,
            'requires_proof' => $this->requiresProof(),
            'account' => $this->accountDetails(),
            'notes' => $this->method->instructionValue('notes'),
        ];
    }

    /**
     * The subset of `payment_methods.instructions` this method actually needs,
     * so the API never leaks unrelated keys an admin left in the JSON. A field
     * the admin has not filled in comes back as null rather than missing, so
     * the shape of the payload does not depend on how complete the config is.
     *
     * @return array<string, mixed>
     */
    protected function accountDetails(): array
    {
        $details = [];

        foreach ($this->type()->instructionFields() as $field) {
            $details[$field] = $this->method->instructionValue($field);
        }

        return $details;
    }
}
