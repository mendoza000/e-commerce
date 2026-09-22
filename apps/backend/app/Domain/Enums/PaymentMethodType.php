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
}
