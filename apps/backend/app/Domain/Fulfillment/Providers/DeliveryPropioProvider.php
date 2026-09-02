<?php

namespace App\Domain\Fulfillment\Providers;

use App\Domain\Enums\FulfillmentMethodType;

class DeliveryPropioProvider extends ManualFulfillmentProvider
{
    public function type(): FulfillmentMethodType
    {
        return FulfillmentMethodType::DeliveryPropio;
    }
}
