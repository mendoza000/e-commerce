<?php

namespace App\Domain\Enums;

enum InventoryMovementType: string
{
    case Sale = 'sale';
    case Release = 'release';
    case Adjustment = 'adjustment';
    case Reservation = 'reservation';

    /**
     * Units put back on the shelf because a sale that had already been
     * committed was cancelled. Deliberately not folded into Release (which
     * only frees a reservation and never touches `stock`) nor into Adjustment
     * (a human correcting a physical count): the kardex has to say why the
     * stock moved, not just that it did.
     */
    case Restock = 'restock';

    /**
     * What the panel prints in the kardex. Lives here rather than in the
     * frontend so the wording of a movement type cannot drift away from the
     * meaning the backend gives it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Reservation => 'Reserva',
            self::Sale => 'Venta',
            self::Release => 'Liberación de reserva',
            self::Restock => 'Reingreso por cancelación',
            self::Adjustment => 'Ajuste manual',
        };
    }
}
