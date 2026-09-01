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
 * Subclasses only declare which fields of the JSON blob they expose.
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
     * so the API never leaks unrelated keys an admin left in the JSON.
     *
     * @return array<string, mixed>
     */
    abstract protected function accountDetails(): array;
}
