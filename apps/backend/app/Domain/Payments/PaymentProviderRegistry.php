<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentProviderInterface;
use App\Models\PaymentMethod;

/**
 * Turns a stored payment method row into its provider object.
 *
 * Nothing outside the domain calls this directly: use
 * PaymentMethod::provider(), which reads better at the call site.
 */
class PaymentProviderRegistry
{
    public function for(PaymentMethod $method): PaymentProviderInterface
    {
        $class = $method->type->providerClass();

        return new $class($method);
    }
}
