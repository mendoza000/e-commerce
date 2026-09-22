<?php

namespace App\Domain\Fulfillment\Providers;

use App\Domain\Enums\FulfillmentMethodType;

class CourierManualProvider extends ManualFulfillmentProvider
{
    public function type(): FulfillmentMethodType
    {
        return FulfillmentMethodType::CourierManual;
    }
}
