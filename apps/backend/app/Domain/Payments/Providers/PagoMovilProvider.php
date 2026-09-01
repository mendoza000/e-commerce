<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Enums\PaymentMethodType;

class PagoMovilProvider extends ManualPaymentProvider
{
    public function type(): PaymentMethodType
    {
        return PaymentMethodType::PagoMovil;
    }

    protected function accountDetails(): array
    {
        return [
            'bank' => $this->method->instructionValue('bank'),
            'bank_code' => $this->method->instructionValue('bank_code'),
            'phone' => $this->method->instructionValue('phone'),
            'document_number' => $this->method->instructionValue('document_number'),
        ];
    }
}
