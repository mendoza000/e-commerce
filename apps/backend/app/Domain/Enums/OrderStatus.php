<?php

namespace App\Domain\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentSubmitted = 'payment_submitted';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * The whole order lifecycle, in one place. Read top to bottom to know every
     * move the business allows; anything not listed here is impossible by
     * construction (see Order::transitionTo).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // The customer either uploads a proof, or the reservation expires.
            self::PendingPayment => [self::PaymentSubmitted, self::Cancelled],
            // Back to PendingPayment means the admin rejected the proof and the
            // customer gets another shot at paying.
            self::PaymentSubmitted => [self::Paid, self::PendingPayment, self::Cancelled],
            self::Paid => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            // Terminal states.
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * True while the order is still holding a stock reservation rather than a
     * confirmed sale — that is, while the reservation can still expire.
     */
    public function holdsReservation(): bool
    {
        return in_array($this, [self::PendingPayment, self::PaymentSubmitted], true);
    }

    /**
     * True once confirmPayment turned the reservation into a definitive
     * deduction from `stock`. Cancelling from here has to put the units back,
     * not release a reservation that no longer exists — see Order::cancel.
     */
    public function hasCommittedStock(): bool
    {
        return in_array($this, [self::Paid, self::Preparing, self::Shipped, self::Delivered], true);
    }

    /**
     * The statuses that only move an already-paid order along its way to the
     * customer. They are the only targets the generic admin transition
     * endpoint accepts: every other move carries a money or inventory side
     * effect and therefore has its own endpoint (see Order::confirmPayment,
     * Order::rejectPayment and Order::cancel).
     *
     * @return array<int, self>
     */
    public static function fulfillmentStatuses(): array
    {
        return [self::Preparing, self::Shipped, self::Delivered];
    }

    /**
     * The subset of allowedTransitions() the admin panel may perform through
     * the generic transition endpoint, so the UI can offer exactly the buttons
     * that will work.
     *
     * @return array<int, self>
     */
    public function fulfillmentTransitions(): array
    {
        return array_values(array_filter(
            $this->allowedTransitions(),
            fn (self $target) => in_array($target, self::fulfillmentStatuses(), true),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::PaymentSubmitted => 'Comprobante enviado',
            self::Paid => 'Pagada',
            self::Preparing => 'En preparación',
            self::Shipped => 'Enviada',
            self::Delivered => 'Entregada',
            self::Cancelled => 'Cancelada',
        };
    }
}
