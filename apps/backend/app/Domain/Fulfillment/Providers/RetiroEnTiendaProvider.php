<?php

namespace App\Domain\Fulfillment\Providers;

use App\Domain\Enums\FulfillmentMethodType;
use App\Domain\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Models\FulfillmentMethod;
use App\Models\Municipality;
use App\Models\State;

/**
 * Picking up in person has no destination to price and nothing to hand a
 * courier: it always costs nothing and never carries a tracking code,
 * regardless of what zone rates the store configured for other methods.
 */
class RetiroEnTiendaProvider implements FulfillmentProviderInterface
{
    public function __construct(protected readonly FulfillmentMethod $method) {}

    public function type(): FulfillmentMethodType
    {
        return FulfillmentMethodType::RetiroEnTienda;
    }

    public function label(): string
    {
        return $this->method->label;
    }

    public function requiresTrackingCode(): bool
    {
        return false;
    }

    public function estimateCost(?State $state, ?Municipality $municipality): ?string
    {
        return '0.000000';
    }
}
