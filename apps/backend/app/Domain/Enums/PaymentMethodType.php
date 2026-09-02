<?php

namespace App\Domain\Enums;

use App\Domain\Payments\Contracts\PaymentProviderInterface;
use App\Domain\Payments\Providers\EfectivoContraEntregaProvider;
use App\Domain\Payments\Providers\PagoMovilProvider;
use App\Domain\Payments\Providers\TransferenciaNacionalProvider;
use App\Domain\Payments\Providers\ZelleProvider;

enum PaymentMethodType: string
{
    case PagoMovil = 'pago_movil';
    case Zelle = 'zelle';
    case TransferenciaNacional = 'transferencia_nacional';
    case EfectivoContraEntrega = 'efectivo_contra_entrega';

    /**
     * The single source of truth for type => provider class. Adding a payment
     * method means adding a case here and a provider class — nothing else in
     * the application needs to change.
     *
     * @return class-string<PaymentProviderInterface>
     */
    public function providerClass(): string
    {
        return match ($this) {
            self::PagoMovil => PagoMovilProvider::class,
            self::Zelle => ZelleProvider::class,
            self::TransferenciaNacional => TransferenciaNacionalProvider::class,
            self::EfectivoContraEntrega => EfectivoContraEntregaProvider::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PagoMovil => 'Pago Móvil',
            self::Zelle => 'Zelle',
            self::TransferenciaNacional => 'Transferencia nacional',
            self::EfectivoContraEntrega => 'Efectivo contra entrega',
        };
    }

    /**
     * The account fields this method's `instructions` JSON is expected to
     * carry — a phone for Pago Móvil, an email for Zelle, and so on.
     *
     * One list, read from two directions: ManualPaymentProvider builds the
     * customer-facing account block from it, and the admin API both validates
     * against it and hands it to the panel so the form can draw the right
     * fields per type. Keeping the two in sync by construction is why the list
     * lives here and not inside each provider.
     *
     * @return array<int, string>
     */
    public function instructionFields(): array
    {
        return match ($this) {
            self::PagoMovil => ['bank', 'bank_code', 'phone', 'document_number'],
            self::Zelle => ['email', 'holder_name'],
            self::TransferenciaNacional => ['bank', 'account_number', 'account_type', 'holder_name', 'document_number'],
            self::EfectivoContraEntrega => ['contact_phone'],
        };
    }
}
