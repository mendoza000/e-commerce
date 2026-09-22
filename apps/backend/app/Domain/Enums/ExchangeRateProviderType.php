<?php

namespace App\Domain\Enums;

use App\Domain\ExchangeRates\Contracts\ExchangeRateProviderInterface;
use App\Domain\ExchangeRates\Providers\CriptoYaRateProvider;
use App\Domain\ExchangeRates\Providers\ManualRateProvider;

enum ExchangeRateProviderType: string
{
    case Manual = 'manual';
    case CriptoYa = 'criptoya';

    /**
     * @return class-string<ExchangeRateProviderInterface>
     */
    public function providerClass(): string
    {
        return match ($this) {
            self::Manual => ManualRateProvider::class,
            self::CriptoYa => CriptoYaRateProvider::class,
        };
    }
}
