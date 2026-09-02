<?php

namespace App\Domain\Fulfillment;

use App\Domain\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Models\FulfillmentMethod;

/**
 * Turns a stored fulfillment method row into its provider object.
 *
 * Nothing outside the domain calls this directly: use
 * FulfillmentMethod::provider(), which reads better at the call site. Mirrors
 * PaymentProviderRegistry exactly.
 */
class FulfillmentProviderRegistry
{
    public function for(FulfillmentMethod $method): FulfillmentProviderInterface
    {
        $class = $method->type->providerClass();

        return new $class($method);
    }
}
