<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Enums\PaymentMethodType;

class TransferenciaNacionalProvider extends ManualPaymentProvider
{
    public function type(): PaymentMethodType
    {
        return PaymentMethodType::TransferenciaNacional;
    }

    protected function accountDetails(): array
    {
        return [
            'bank' => $this->method->instructionValue('bank'),
            'account_number' => $this->method->instructionValue('account_number'),
            'account_type' => $this->method->instructionValue('account_type'),
            'holder_name' => $this->method->instructionValue('holder_name'),
            'document_number' => $this->method->instructionValue('document_number'),
        ];
    }
}
