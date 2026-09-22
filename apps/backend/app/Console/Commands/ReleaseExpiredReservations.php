<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:release-expired-reservations')]
#[Description('Cancels orders whose stock reservation has expired and releases their reserved inventory.')]
class ReleaseExpiredReservations extends Command
{
    /**
     * Sweeps both pending_payment orders (never paid) and payment_submitted
     * ones (a proof was uploaded but nobody reviewed it within the much longer
     * commerce.payment_review_minutes window). Which statuses qualify is
     * decided by OrderStatus::holdsReservation, via the scope below.
     *
     * All the locking, releasing and history writing lives on the Order model;
     * this command only decides what to sweep and reports the outcome.
     */
    public function handle(): int
    {
        $expiredOrderIds = Order::query()->withExpiredReservation()->pluck('id');

        $released = 0;

        foreach ($expiredOrderIds as $orderId) {
            $order = Order::query()->find($orderId);

            // Already resolved between the query and now: idempotent no-op.
            if ($order?->cancelExpiredReservation('La reserva de inventario expiró sin pago confirmado.')) {
                $released++;
            }
        }

        $this->info("Released {$released} expired reservation(s).");

        return self::SUCCESS;
    }
}
