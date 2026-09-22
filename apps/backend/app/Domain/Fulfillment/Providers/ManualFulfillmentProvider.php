<?php

namespace App\Domain\Fulfillment\Providers;

use App\Domain\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Models\FulfillmentMethod;
use App\Models\Municipality;
use App\Models\State;

/**
 * Shared pricing behaviour of the zone-priced methods (delivery propio and
 * courier manual): look up the most specific configured rate for the
 * destination — municipality beats state, state beats nothing — and fall
 * back to the method's flat `base_cost` when no zone-specific row exists.
 *
 * Retiro en tienda does not extend this: pickup has no destination to price.
 */
abstract class ManualFulfillmentProvider implements FulfillmentProviderInterface
{
    public function __construct(protected readonly FulfillmentMethod $method) {}

    public function label(): string
    {
        return $this->method->label;
    }

    /**
     * Stored on the method rather than fixed per type, so an admin can decide
     * per store whether a given courier's tracking number is worth asking for.
     */
    public function requiresTrackingCode(): bool
    {
        return $this->method->requires_tracking_code;
    }

    public function estimateCost(?State $state, ?Municipality $municipality): ?string
    {
        $zoneRate = $this->method->zoneRateFor($state, $municipality);

        if ($zoneRate !== null) {
            // An explicit row with no cost means the admin priced this exact
            // zone as "a coordinar" — distinct from no row existing at all.
            return $zoneRate->cost === null ? null : (string) $zoneRate->cost;
        }

        return $this->method->base_cost === null ? null : (string) $this->method->base_cost;
    }
}
