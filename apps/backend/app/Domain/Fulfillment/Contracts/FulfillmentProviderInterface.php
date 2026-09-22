<?php

namespace App\Domain\Fulfillment\Contracts;

use App\Domain\Enums\FulfillmentMethodType;
use App\Models\Municipality;
use App\Models\State;

/**
 * One implementation per shipping method (see PRD section 8).
 *
 * A provider knows only how to describe itself and price a destination — it
 * never touches order status or inventory, and it never writes anything: the
 * quote it returns is what OrderService freezes onto the order.
 */
interface FulfillmentProviderInterface
{
    public function type(): FulfillmentMethodType;

    public function label(): string;

    /**
     * Whether a tracking/guide number is meaningful for this method. A UI
     * hint exposed on the resource, exactly like PaymentProviderInterface's
     * requiresProof() — not enforced here, because PRD section 6 describes the
     * courier's tracking number as free-form ("si el cliente lo obtiene").
     */
    public function requiresTrackingCode(): bool;

    /**
     * Estimated cost to ship to a destination, in this method's currency.
     * Null means "a coordinar": the store has not priced this zone, and the
     * amount is settled outside the system rather than blocking checkout.
     */
    public function estimateCost(?State $state, ?Municipality $municipality): ?string;
}
