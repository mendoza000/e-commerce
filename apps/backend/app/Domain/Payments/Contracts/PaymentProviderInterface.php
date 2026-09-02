<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Enums\PaymentMethodType;
use App\Models\Currency;
use App\Models\Order;
use App\Models\User;

/**
 * One implementation per payment method (see PRD section 7).
 *
 * A provider knows only how to *describe* and *confirm* its own way of getting
 * paid. It never touches order status or inventory — that lives on the Order
 * model, which is the same for every method.
 */
interface PaymentProviderInterface
{
    public function type(): PaymentMethodType;

    /**
     * The currency this method charges in. An order paid with this method is
     * always frozen in this currency, never in one the client picked.
     */
    public function getCurrency(): Currency;

    /**
     * What the customer must be shown at checkout: the account details plus the
     * amount already converted to this method's currency.
     *
     * @return array<string, mixed>
     */
    public function getInstructions(Order $order): array;

    public function requiresProof(): bool;

    /**
     * Provider-specific side effects when an admin confirms the payment.
     * Manual providers have none; this is the seam where an automated provider
     * (e.g. a future BinancePayProvider) would capture or verify the payment.
     */
    public function confirm(Order $order, User $admin): void;
}
