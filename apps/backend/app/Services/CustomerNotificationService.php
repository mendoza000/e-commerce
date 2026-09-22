<?php

namespace App\Services;

use App\Domain\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderStatusUpdated;

/**
 * The customer-facing half of order notifications (PRD section 5): payment
 * confirmed, shipped, delivered. Deliberately narrower than
 * PaymentProofService::notifyAdmins() — an admin account always has an email,
 * but a guest checkout (the norm, per docs/decisions.md) never captures one,
 * so there is nothing to notify until the customer registers.
 */
class CustomerNotificationService
{
    /**
     * @var array<int, OrderStatus>
     */
    private const NOTIFIABLE_STATUSES = [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered];

    public function notifyStatusChange(Order $order): void
    {
        if (! in_array($order->status, self::NOTIFIABLE_STATUSES, true)) {
            return;
        }

        $customer = $order->customer_id !== null ? $order->loadMissing('customer')->customer : null;

        // Known gap, not a bug: a guest order has no email to send to, and a
        // wa.me link cannot be pushed without the customer clicking it
        // themselves — see docs/decisions.md.
        if ($customer === null || $customer->email === null) {
            return;
        }

        $customer->notify(new OrderStatusUpdated($order));
    }
}
