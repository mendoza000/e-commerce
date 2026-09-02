<?php

namespace App\Domain\Enums;

use App\Domain\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Domain\Fulfillment\Providers\CourierManualProvider;
use App\Domain\Fulfillment\Providers\DeliveryPropioProvider;
use App\Domain\Fulfillment\Providers\RetiroEnTiendaProvider;

enum FulfillmentMethodType: string
{
    case DeliveryPropio = 'delivery_propio';
    case RetiroEnTienda = 'retiro_en_tienda';
    case CourierManual = 'courier_manual';

    /**
     * The single source of truth for type => provider class, same role as
     * PaymentMethodType::providerClass(). Adding a shipping method means
     * adding a case here and a provider class — nothing else changes.
     *
     * @return class-string<FulfillmentProviderInterface>
     */
    public function providerClass(): string
    {
        return match ($this) {
            self::DeliveryPropio => DeliveryPropioProvider::class,
            self::RetiroEnTienda => RetiroEnTiendaProvider::class,
            self::CourierManual => CourierManualProvider::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DeliveryPropio => 'Delivery propio',
            self::RetiroEnTienda => 'Retiro en tienda',
            self::CourierManual => 'Courier (MRW, Zoom, Tealca, etc.)',
        };
    }
}
