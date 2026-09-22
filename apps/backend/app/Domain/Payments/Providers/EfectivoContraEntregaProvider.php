<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Enums\PaymentMethodType;

/**
 * Currency is whatever the admin configured on the payment method row (Bs or
 * USD, per PRD section 7) — no special casing needed, the base class already
 * reads it from the model.
 */
class EfectivoContraEntregaProvider extends ManualPaymentProvider
{
    public function type(): PaymentMethodType
    {
        return PaymentMethodType::EfectivoContraEntrega;
    }

    /**
     * Cash is handed over in person, so there is nothing to prove beforehand.
     */
    public function requiresProof(): bool
    {
        return false;
    }

    protected function accountDetails(): array
    {
        return [
            'contact_phone' => $this->method->instructionValue('contact_phone'),
        ];
    }
}
