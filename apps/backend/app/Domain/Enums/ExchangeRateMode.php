<?php

namespace App\Domain\Enums;

enum ExchangeRateMode: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
