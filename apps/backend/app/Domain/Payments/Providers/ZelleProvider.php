<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Enums\PaymentMethodType;

class ZelleProvider extends ManualPaymentProvider
{
    public function type(): PaymentMethodType
    {
        return PaymentMethodType::Zelle;
    }

    protected function accountDetails(): array
    {
        return [
            'email' => $this->method->instructionValue('email'),
            'holder_name' => $this->method->instructionValue('holder_name'),
        ];
    }
}
