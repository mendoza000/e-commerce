<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Enums\PaymentMethodType;

class TransferenciaNacionalProvider extends ManualPaymentProvider
{
    public function type(): PaymentMethodType
    {
        return PaymentMethodType::TransferenciaNacional;
    }
}
