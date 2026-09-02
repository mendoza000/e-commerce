<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Enums\PaymentMethodType;

class ZelleProvider extends ManualPaymentProvider
{
    public function type(): PaymentMethodType
    {
        return PaymentMethodType::Zelle;
    }
}
