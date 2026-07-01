<?php

namespace App\Domain\Enums;

enum InventoryMovementType: string
{
    case Sale = 'sale';
    case Release = 'release';
    case Adjustment = 'adjustment';
}
